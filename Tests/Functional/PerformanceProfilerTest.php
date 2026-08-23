<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\DBAL\Driver\Middleware as DbalMiddleware;
use Jul6Art\CoreBundle\Performance\EventSubscriber\PerformanceSubscriber;
use Jul6Art\CoreBundle\Performance\Profiler\Middleware\PerformanceMiddleware;
use Jul6Art\CoreBundle\Performance\Profiler\QueryHasher;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;
use Jul6Art\CoreBundle\Performance\Service\DashboardViewBuilder;
use Jul6Art\CoreBundle\Performance\Store\JsonlFileStore;
use Jul6Art\CoreBundle\Performance\Store\PerformanceRecord;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use PHPUnit\Framework\Attributes\CoversNothing;

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
