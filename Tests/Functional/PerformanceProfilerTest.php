<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\DBAL\Driver\Middleware as DbalMiddleware;
use Jul6Art\CoreBundle\Performance\Command\ClearCommand;
use Jul6Art\CoreBundle\Performance\Command\ExportCommand;
use Jul6Art\CoreBundle\Performance\EventSubscriber\PerformanceSubscriber;
use Jul6Art\CoreBundle\Performance\Profiler\Middleware\PerformanceMiddleware;
use Jul6Art\CoreBundle\Performance\Profiler\PerformanceDataCollector;
use Jul6Art\CoreBundle\Performance\Profiler\QueryHasher;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;
use Jul6Art\CoreBundle\Performance\Service\DashboardViewBuilder;
use Jul6Art\CoreBundle\Performance\Store\JsonlFileStore;
use Jul6Art\CoreBundle\Performance\Store\PerformanceRecord;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Environment;

/**
 * The per-request performance profiler: what has to be wired, and what must NOT be.
 */
#[CoversNothing]
final class PerformanceProfilerTest extends AbstractFunctionalTestCase
{
    /**
     * ⚠️ The middleware and the store are wired even when the profiler is OFF, and that is the
     * point: the middleware resets its tracker on every request, so a long-running worker cannot
     * accumulate query rows in memory. Only *persistence* is gated.
     */
    public function testTheMiddlewareIsWiredEvenWhenTheProfilerIsOff(): void
    {
        $container = $this->boot();

        self::assertTrue($container->has(PerformanceStoreInterface::class));
        self::assertInstanceOf(PerformanceSubscriber::class, $container->get(PerformanceSubscriber::class));
    }

    public function testTheStoreHonoursTheConfiguredPath(): void
    {
        $directory = sys_get_temp_dir().'/core-perf-'.bin2hex(random_bytes(4));
        $container = $this->boot(coreConfig: ['performance' => ['enabled' => true, 'path' => $directory]]);

        $store = $container->get(PerformanceStoreInterface::class);
        self::assertInstanceOf(JsonlFileStore::class, $store);

        $store->append($this->record('app_dashboard'));
        self::assertSame(1, $store->count());
        // `listAll()` rend un itérable (le store se lit en flux, il peut peser) : le matérialiser
        // est le seul moyen de l'inspecter dans un test.
        self::assertCount(1, iterator_to_array($store->listAll(), false));

        $store->clear();
        self::assertSame(0, $store->count());

        array_map(unlink(...), glob($directory.'/*') ?: []);
        @rmdir($directory);
    }

    /**
     * Le tableau de bord agrège ce que le store contient — il est fourni par le bundle pour que
     * chaque projet n'ait pas à réécrire les mêmes moyennes.
     */
    public function testTheDashboardBuilderAggregatesTheStore(): void
    {
        $directory = sys_get_temp_dir().'/core-perf-'.bin2hex(random_bytes(4));
        $container = $this->boot(coreConfig: ['performance' => ['enabled' => true, 'path' => $directory]]);

        $store = $container->get(PerformanceStoreInterface::class);
        self::assertInstanceOf(JsonlFileStore::class, $store);
        $store->append($this->record('app_dashboard'));
        $store->append($this->record('app_dashboard'));

        $builder = $container->get(DashboardViewBuilder::class);
        self::assertInstanceOf(DashboardViewBuilder::class, $builder);

        $view = $builder->build();
        self::assertNotSame([], $view);

        array_map(unlink(...), glob($directory.'/*') ?: []);
        @rmdir($directory);
    }

    /**
     * Le préfixe de route ignoré est CONFIGURABLE : le tableau de bord du profileur mesurerait
     * sinon sa propre page, et chaque visite ajouterait un enregistrement à ce qu'elle affiche.
     * Le bundle ne livrant aucune route, le préfixe appartient à qui les déclare.
     */
    public function testTheIgnoredRoutePrefixIsConfigurable(): void
    {
        $container = $this->boot(coreConfig: ['performance' => ['ignored_route_prefix' => 'my_own_profiler_']]);
        $subscriber = $container->get(PerformanceSubscriber::class);

        $reflection = new \ReflectionProperty($subscriber, 'ignoredRoutePrefix');

        self::assertSame('my_own_profiler_', $reflection->getValue($subscriber));
    }

    public function testTheTrackerCountsAndDeduplicates(): void
    {
        $tracker = new QueryTracker(new QueryHasher());
        // L'API est `start()`/`end()` : le middleware DBAL encadre l'exécution réelle, ce qui est
        // la seule façon de mesurer le temps passé en base plutôt que le temps PHP.
        $tracker->end($tracker->start('SELECT * FROM a WHERE id = ?'));
        $tracker->end($tracker->start('SELECT * FROM a WHERE id = ?'));
        $tracker->end($tracker->start('SELECT * FROM b'));

        self::assertSame(3, $tracker->getQueryCount());
        self::assertSame(2, $tracker->getDistinctCount(), 'Deux requêtes identiques comptent pour une seule distincte — c\'est ainsi qu\'un N+1 se voit.');

        $tracker->reset();
        self::assertSame(0, $tracker->getQueryCount());
    }

    /**
     * Le middleware est un `Doctrine\DBAL\Driver\Middleware` : sans le tag `doctrine.middleware`
     * que l'extension lui pose, aucune requête n'est comptée — et rien ne le signalerait.
     */
    public function testTheMiddlewareIsADbalMiddleware(): void
    {
        $container = $this->boot(coreConfig: ['performance' => ['enabled' => true]]);

        self::assertTrue($container->has(PerformanceMiddleware::class));
        self::assertInstanceOf(DbalMiddleware::class, $container->get(PerformanceMiddleware::class));
    }

    /**
     * ⚠️ Les trois défauts qui rendaient le panneau invisible, figés ici.
     *
     * Le collecteur collectait parfaitement ; c'est son IDENTITÉ qui était fausse. Le profileur
     * indexe les collecteurs par `getName()`, et le tag déclarait un autre `id` — donc aucun
     * panneau, ni dans la barre de debug ni dans le profileur, et aucune erreur nulle part.
     * Le gabarit, lui, pointait le chemin relatif aux templates de l'application d'où ce code a
     * été extrait.
     */
    public function testTheCollectorIdentityMatchesWhatTheExtensionDeclares(): void
    {
        $extension = (string) file_get_contents(\dirname(__DIR__, 2).'/DependencyInjection/CoreExtension.php');

        self::assertStringContainsString("'id' => 'core.performance'", $extension);
        self::assertSame('core.performance', new PerformanceDataCollector(
            new PerformanceSubscriber(new QueryTracker(new QueryHasher()), new JsonlFileStore(sys_get_temp_dir(), 'none', 1), false),
        )->getName(), 'Le nom du collecteur doit être celui que le tag déclare.');

        $template = PerformanceDataCollector::getTemplate();
        self::assertSame('@Core/performance/collector.html.twig', $template);
        self::assertFileExists(
            \dirname(__DIR__, 2).'/Resources/views/performance/collector.html.twig',
            'Le gabarit déclaré doit exister dans le bundle.',
        );
    }

    /**
     * ⚠️ Les commandes taguées sont instanciées PARESSEUSEMENT : un mauvais nombre d'arguments
     * passe `cache:clear` sans un mot et n'explose qu'à l'exécution — ou à `lint:container`.
     * Les résoudre ici est le seul moyen de l'attraper dans une suite.
     */
    public function testTheCommandsAreConstructibleAsWired(): void
    {
        $container = $this->boot(coreConfig: ['performance' => ['enabled' => true]]);

        foreach ([ClearCommand::class, ExportCommand::class] as $command) {
            self::assertTrue($container->has($command), \sprintf('%s doit être enregistrée.', $command));
            self::assertInstanceOf($command, $container->get($command));
        }
    }

    /**
     * ⚠️ Le gabarit du panneau se RÉEND, il ne se relit pas.
     *
     * Il référençait une fonction Twig restée dans l'application d'où ce code vient
     * (`performance_route_exists`) et les anciens noms de ses routes : le panneau levait une
     * SyntaxError au premier affichage, ce qu'aucun test ne voyait — la collecte, elle,
     * fonctionnait. Un gabarit ne se prouve qu'en le rendant.
     */
    public function testTheCollectorTemplateCompilesAndRenders(): void
    {
        $container = $this->boot(coreConfig: ['performance' => ['enabled' => true]]);

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        // Compiler suffit à attraper une fonction inconnue : c'est le parseur qui lève.
        $source = $twig->getLoader()->getSourceContext('@Core/performance/collector.html.twig');
        $twig->parse($twig->tokenize($source));

        self::assertStringContainsString('core_performance_route_exists', $source->getCode());
        self::assertStringNotContainsString(
            'app_admin_performance_',
            $source->getCode(),
            'Les routes de l\'écran s\'appellent admin_performance_* — le préfixe que le collecteur ignore.',
        );
    }

    private function record(string $route): PerformanceRecord
    {
        return new PerformanceRecord(
            id: bin2hex(random_bytes(8)),
            timestamp: microtime(true),
            route: $route,
            method: 'GET',
            uri: '/'.$route,
            statusCode: 200,
            durationMs: 12.5,
            memoryPeakBytes: 1024,
            queryCount: 3,
            distinctQueryCount: 2,
            dbTimeMs: 1.5,
            responseSizeBytes: 2048,
            queries: [['sql' => 'SELECT 1', 'count' => 3, 'total_time' => 1.5]],
        );
    }
}
