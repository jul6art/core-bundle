<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\DependencyInjection;

use Jul6Art\CoreBundle\Command\JsTranslationAuditCommand;
use Jul6Art\CoreBundle\Command\PurgeCommand;
use Jul6Art\CoreBundle\Controller\BulkActionRunner;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedTypeRegistrar;
use Jul6Art\CoreBundle\EventListener\SecurityHeaderListener;
use Jul6Art\CoreBundle\Form\Extension\NumberTypeGroupingExtension;
use Jul6Art\CoreBundle\Logger\QueryStringRedactingProcessor;
use Jul6Art\CoreBundle\Performance\CacheClearer\PerformanceStoreClearer;
use Jul6Art\CoreBundle\Performance\CacheWarmer\PerformanceStoreWarmer;
use Jul6Art\CoreBundle\Performance\Command\ClearCommand;
use Jul6Art\CoreBundle\Performance\Command\ExportCommand;
use Jul6Art\CoreBundle\Performance\EventSubscriber\PerformanceSubscriber;
use Jul6Art\CoreBundle\Performance\Profiler\Middleware\PerformanceMiddleware;
use Jul6Art\CoreBundle\Performance\Profiler\PerformanceDataCollector;
use Jul6Art\CoreBundle\Performance\Profiler\QueryHasher;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;
use Jul6Art\CoreBundle\Performance\Service\DashboardViewBuilder;
use Jul6Art\CoreBundle\Performance\Service\PerformanceExporter;
use Jul6Art\CoreBundle\Performance\Store\JsonlFileStore;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Jul6Art\CoreBundle\Security\Encryptor;
use Jul6Art\CoreBundle\Security\MathCaptchaService;
use Jul6Art\CoreBundle\Service\CascadeSoftDeleteHelper;
use Jul6Art\CoreBundle\Service\FlashTranslator;
use Jul6Art\CoreBundle\Service\NumberFormatter;
use Jul6Art\CoreBundle\Twig\NumberExtension;
use Jul6Art\CoreBundle\Twig\PerformanceExtension;
use Monolog\Formatter\HtmlFormatter;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Extension\AbstractExtension;

/**
 * Class CoreExtension.
 *
 * @phpstan-type CoreConfig array{
 *     email_debug: bool,
 *     email_debug_from: string|null,
 *     email_debug_title: string|null,
 *     email_debug_to: string|null,
 * }
 *
 * `encryption_key` is intentionally absent from CoreConfig: prepend() turns every key of
 * that shape into a container parameter, and the key must never be exposed that way. It is
 * read in load() only.
 */
class CoreExtension extends Extension implements PrependExtensionInterface
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');

        // Deliberately the *unprocessed* configuration: an `%env(...)%` placeholder must
        // reach the service argument untouched so the secret is read at runtime instead of
        // being baked into the compiled container.
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerEncryption($container, \is_string($config['encryption_key'] ?? null) ? $config['encryption_key'] : null);
        $this->registerSecurityHeaders($container, \is_array($config['security_headers'] ?? null) ? $config['security_headers'] : []);
        $this->registerCaptcha($container, \is_array($config['captcha'] ?? null) ? $config['captcha'] : []);
        $this->registerFormatting($container, $config);
        $this->registerFlashTranslator($container, \is_array($config['flash'] ?? null) ? $config['flash'] : []);
        $this->registerDoctrineServices($container, self::purgeBatchSize($config), self::purgeAliases($config));
        $this->registerPerformance($container, \is_array($config['performance'] ?? null) ? $config['performance'] : []);
        $this->registerJsTranslationAudit($container);
        $this->registerLogRedaction($container, \is_array($config['log_redaction'] ?? null) ? $config['log_redaction'] : []);
    }

    /**
     * The query-string redactor, for every Monolog channel. Needs Monolog, which this bundle only
     * suggests: without it there is no log to protect.
     *
     * ⚠️ Tagged `monolog.processor` with no channel and no handler, so it runs on EVERY record: the
     * same URI is logged by the router, the kernel and the error handler, on three channels, and a
     * redactor covering only the door it was written for is the shape of leak that comes back.
     *
     * @param array<mixed> $config
     */
    private function registerLogRedaction(ContainerBuilder $container, array $config): void
    {
        if (false === ($config['enabled'] ?? true) || !class_exists(LogRecord::class)) {
            return;
        }

        $parameters = $config['parameters'] ?? QueryStringRedactingProcessor::DEFAULT_PARAMETERS;

        $container->register(QueryStringRedactingProcessor::class, QueryStringRedactingProcessor::class)
            ->setArguments([\is_array($parameters) ? array_values(array_filter($parameters, \is_string(...))) : QueryStringRedactingProcessor::DEFAULT_PARAMETERS])
            ->addTag('monolog.processor');
    }

    /**
     * The audit command. Needs symfony/console, which this bundle only suggests.
     *
     * ⚠️ It is registered whether or not `symfony/ux-translator` is installed: a project migrating
     * TO the single domain has to be able to see what is left to move BEFORE it installs the
     * package, and that is the moment the command earns its keep.
     */
    private function registerJsTranslationAudit(ContainerBuilder $container): void
    {
        if (!class_exists(Command::class)) {
            return;
        }

        $container->register(JsTranslationAuditCommand::class, JsTranslationAuditCommand::class)
            ->setArguments([
                new Reference('translator'),
                '%kernel.project_dir%',
                '%core.js_translations.domain%',
                '%kernel.enabled_locales%',
            ])
            ->addTag('console.command')
            ->setPublic(true);
    }

    /**
     * The per-request performance profiler.
     *
     * ⚠️ The DBAL middleware and the store are registered **even when `enabled` is false**, and
     * that is deliberate: the middleware resets its tracker on every request, so a long-running
     * worker cannot accumulate query rows in memory, and switching the flag on needs no cache
     * warm-up beyond the usual one. What `enabled` gates is *persistence* — the subscriber
     * writes nothing, and the panel stays empty.
     *
     * The data collector is registered only when FrameworkBundle is installed: it extends that
     * package's AbstractDataCollector, which this bundle only suggests. Referencing the class
     * unconditionally would make the container unbuildable in an application that took the
     * bundle for its entities alone.
     *
     * @param array<mixed> $config
     */
    private function registerPerformance(ContainerBuilder $container, array $config): void
    {
        $enabled = (bool) ($config['enabled'] ?? false);
        $path = \is_string($config['path'] ?? null) ? $config['path'] : '%kernel.project_dir%/var/performance';
        $rotation = \is_string($config['rotation'] ?? null) ? $config['rotation'] : 'daily';
        $maxRecords = \is_int($config['max_records'] ?? null) ? $config['max_records'] : 100000;
        $ignoredPrefix = \is_string($config['ignored_route_prefix'] ?? null) ? $config['ignored_route_prefix'] : 'admin_performance_';

        $container->register(JsonlFileStore::class, JsonlFileStore::class)
            ->setArguments(['$directory' => $path, '$rotation' => $rotation, '$maxRecords' => $maxRecords])
            ->setPublic(false);
        $container->setAlias(PerformanceStoreInterface::class, JsonlFileStore::class)->setPublic(true);

        $container->register(QueryHasher::class, QueryHasher::class);
        $container->register(QueryTracker::class, QueryTracker::class)
            ->setArguments([new Reference(QueryHasher::class)])
            ->setPublic(true);

        $container->register(PerformanceMiddleware::class, PerformanceMiddleware::class)
            ->setArguments([new Reference(QueryTracker::class)])
            ->addTag('doctrine.middleware')
            ->setPublic(true);

        $container->register(PerformanceSubscriber::class, PerformanceSubscriber::class)
            ->setArguments([
                new Reference(QueryTracker::class),
                new Reference(PerformanceStoreInterface::class),
                $enabled,
                $ignoredPrefix,
            ])
            ->addTag('kernel.event_subscriber')
            ->setPublic(true);

        $container->register(PerformanceExporter::class, PerformanceExporter::class);
        $container->register(DashboardViewBuilder::class, DashboardViewBuilder::class)
            ->setArguments([new Reference(PerformanceStoreInterface::class)])
            ->setPublic(true);

        $container->register(PerformanceStoreWarmer::class, PerformanceStoreWarmer::class)
            ->setArguments(['$directory' => $path])
            ->addTag('kernel.cache_warmer');

        $container->register(PerformanceStoreClearer::class, PerformanceStoreClearer::class)
            ->setArguments([new Reference(PerformanceStoreInterface::class)])
            ->addTag('kernel.cache_clearer');

        if (class_exists(AbstractDataCollector::class)) {
            $container->register(PerformanceDataCollector::class, PerformanceDataCollector::class)
                ->setArguments([new Reference(PerformanceSubscriber::class)])
                ->addTag('data_collector', [
                    'template' => '@Core/performance/collector.html.twig',
                    'id' => 'core.performance',
                    'priority' => 256,
                ]);
        }

        // Les deux commandes prennent un verrou — purger ou exporter pendant qu'une requête écrit
        // dans le store donnerait un fichier tronqué. Elles n'existent donc que si `symfony/lock`
        // et `symfony/console` sont là, exactement comme `core:purge` ; et le
        // `PerformanceCommandPass` les retire encore si `framework.lock` n'a jamais été configuré,
        // auquel cas la classe existe mais le service `lock.factory` non.
        if (class_exists(Command::class) && class_exists(LockFactory::class)) {
            $container->register(ClearCommand::class, ClearCommand::class)
                ->setArguments([new Reference(PerformanceStoreInterface::class), new Reference('lock.factory')])
                ->addTag('console.command');

            $container->register(ExportCommand::class, ExportCommand::class)
                ->setArguments([
                    new Reference(PerformanceStoreInterface::class),
                    new Reference(PerformanceExporter::class),
                    new Reference('lock.factory'),
                ])
                ->addTag('console.command');
        }
    }

    /**
     * Registered only when switched on: a listener that exists to do nothing on every response
     * is noise in the container, and the headers must never appear unasked.
     *
     * @param array<mixed> $config
     */
    private function registerSecurityHeaders(ContainerBuilder $container, array $config): void
    {
        if (true !== ($config['enabled'] ?? false)) {
            return;
        }

        $headers = $config['headers'] ?? [];

        $container->register(SecurityHeaderListener::class, SecurityHeaderListener::class)
            ->setArguments([
                true,
                // Passed through untouched so an `%env(bool:…)%` placeholder survives to the
                // container and is resolved at runtime; the constructor's bool type does the
                // casting. Coercing it here would turn the placeholder string into `true`.
                $config['csp_enforce'] ?? false,
                \is_string($config['csp_policy'] ?? null) ? $config['csp_policy'] : null,
                \is_array($headers) ? $headers : [],
            ])
            // Priority -100: run after the controllers and the other listeners have set their
            // own headers, since this one only fills the gaps.
            ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => -100]);
    }

    /**
     * The formatter itself needs nothing optional; its Twig filters need Twig, and the form
     * extension needs symfony/form *and* an explicit opt-in — it changes the rendering of every
     * numeric field, which is not a bundle's call to make on installation.
     *
     * @param array<mixed> $config
     */
    private function registerFormatting(ContainerBuilder $container, array $config): void
    {
        $numberFormat = \is_array($config['number_format'] ?? null) ? $config['number_format'] : [];

        $container->register(NumberFormatter::class, NumberFormatter::class)
            ->setArguments([
                self::asStringOr($numberFormat['decimal_separator'] ?? null, ','),
                self::asStringOr($numberFormat['thousands_separator'] ?? null, "\u{00A0}"),
                \is_int($numberFormat['decimals'] ?? null) ? $numberFormat['decimals'] : 2,
            ]);

        if (class_exists(AbstractExtension::class)) {
            // Le panneau du profileur s'en sert pour ne proposer les liens vers l'écran complet
            // que si l'application a importé ses routes. `router` plutôt qu'un paramètre : la
            // réponse dépend de l'environnement, et le panneau ne doit jamais casser la page.
            $container->register(PerformanceExtension::class, PerformanceExtension::class)
                ->setArguments([new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('twig.extension');

            $container->register(NumberExtension::class, NumberExtension::class)
                ->setArguments([new Reference(NumberFormatter::class)])
                ->addTag('twig.extension');
        }

        $form = \is_array($config['form'] ?? null) ? $config['form'] : [];

        if (true === ($form['number_grouping'] ?? false) && class_exists(AbstractTypeExtension::class)) {
            $container->register(NumberTypeGroupingExtension::class, NumberTypeGroupingExtension::class)
                ->addTag('form.type_extension');
        }
    }

    /**
     * Subscribed by {@see \Jul6Art\CoreBundle\Controller\AbstractController}, so it is always
     * registered: `symfony/translation` is a hard requirement of this bundle.
     *
     * @param array<mixed> $config
     */
    private function registerFlashTranslator(ContainerBuilder $container, array $config): void
    {
        $domainMap = $config['domain_map'] ?? [];

        $container->register(FlashTranslator::class, FlashTranslator::class)
            ->setArguments([
                new Reference('translator'),
                \is_array($domainMap) ? $domainMap : [],
                self::asStringOr($config['default_domain'] ?? null, 'messages'),
            ]);
    }

    private static function asStringOr(mixed $value, string $fallback): string
    {
        return \is_string($value) ? $value : $fallback;
    }

    /**
     * @param array<mixed> $config
     */
    private function registerCaptcha(ContainerBuilder $container, array $config): void
    {
        $operations = $config['operations'] ?? ['+'];
        $sessionKey = $config['session_key'] ?? '_math_captcha_answer';

        $container->register(MathCaptchaService::class, MathCaptchaService::class)
            ->setArguments([
                new Reference('request_stack'),
                \is_array($operations) ? array_values($operations) : ['+'],
                \is_string($sessionKey) ? $sessionKey : '_math_captcha_answer',
            ]);
    }

    /**
     * The encryption bricks are opt-in: without a key there is nothing to register, and
     * registering them anyway would make every application boot fail on a missing env var
     * just because the bundle is installed.
     */
    private function registerEncryption(ContainerBuilder $container, ?string $encryptionKey): void
    {
        if (null === $encryptionKey || '' === $encryptionKey) {
            return;
        }

        $container->register(Encryptor::class, Encryptor::class)
            ->setArguments([$encryptionKey]);

        // String event names on purpose: referencing ConsoleEvents::COMMAND would make the
        // listener unloadable in an application without symfony/console, which this bundle
        // does not require.
        $container->register(EncryptedTypeRegistrar::class, EncryptedTypeRegistrar::class)
            ->setArguments([new Reference(Encryptor::class)])
            ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'register', 'priority' => 4096])
            ->addTag('kernel.event_listener', ['event' => 'console.command', 'method' => 'register', 'priority' => 4096]);
    }

    /**
     * Registered only when DoctrineBundle is enabled: `doctrine.orm.entity_manager` does
     * not exist otherwise, and an unresolvable reference would break the container of every
     * application that installs this bundle without the ORM.
     */
    /**
     * Reads the only purge setting the container needs, so the raw config array never has to
     * travel further than this class.
     *
     * @param array<mixed> $config
     */
    private static function purgeBatchSize(array $config): int
    {
        $purge = $config['purge'] ?? null;
        $batchSize = \is_array($purge) ? ($purge['batch_size'] ?? null) : null;

        return \is_int($batchSize) && $batchSize > 0 ? $batchSize : 100;
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<string>
     */
    private static function purgeAliases(array $config): array
    {
        $purge = $config['purge'] ?? null;
        $aliases = \is_array($purge) ? ($purge['aliases'] ?? []) : [];

        return \is_array($aliases) ? array_values(array_filter($aliases, \is_string(...))) : [];
    }

    /**
     * @param list<string> $purgeAliases
     */
    private function registerDoctrineServices(ContainerBuilder $container, int $purgeBatchSize, array $purgeAliases): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['DoctrineBundle'])) {
            return;
        }

        $container->register(CascadeSoftDeleteHelper::class, CascadeSoftDeleteHelper::class)
            ->setArguments([new Reference('doctrine.orm.entity_manager')]);

        $this->registerPurgeCommand($container, $purgeBatchSize, $purgeAliases);
        $this->registerBulkActionRunner($container);
    }

    /**
     * Needs the ORM and symfony/security-csrf: a bulk endpoint without a CSRF check is exactly
     * what this helper exists to prevent, so no token manager means no helper.
     */
    private function registerBulkActionRunner(ContainerBuilder $container): void
    {
        if (!interface_exists(CsrfTokenManagerInterface::class)) {
            return;
        }

        $container->register(BulkActionRunner::class, BulkActionRunner::class)
            ->setArguments([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('security.helper'),
                new Reference('security.csrf.token_manager'),
            ]);
    }

    /**
     * The purge needs symfony/console for the command itself and symfony/lock for the guard
     * that stops two runs racing on the same rows. Both are suggestions, not requirements, so
     * the command only exists when they do — and {@see PurgeCommandPass} drops it again if the
     * lock package is installed but `framework.lock` was never configured.
     *
     * @param list<string> $aliases
     */
    private function registerPurgeCommand(ContainerBuilder $container, int $batchSize, array $aliases): void
    {
        if (!class_exists(Command::class) || !class_exists(LockFactory::class)) {
            return;
        }

        // Aliases travel in the tag value, pipe-separated, the way #[AsCommand(name: 'a|b')]
        // expresses them: a lazily-registered command is never instantiated at compile time,
        // so a setAliases() call would come too late for the console to know the extra names.
        $container->register(PurgeCommand::class, PurgeCommand::class)
            ->setArguments([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('lock.factory'),
                new Reference('event_dispatcher'),
                $batchSize,
            ])
            ->addTag('console.command', ['command' => implode('|', ['core:purge', ...$aliases])]);
    }

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->resolveConfig($container);

        foreach ($config as $key => $parameter) {
            $container->setParameter(\sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }

        $this->prependJsTranslations($container);

        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['MonologBundle'])) {
            return;
        }

        if ('prod' === $container->getParameter('kernel.environment')) {
            $container->prependExtensionConfig('monolog', ['handlers' => $this->buildProdHandlers()]);
        }

        if (true === $config['email_debug']) {
            $container->prependExtensionConfig('monolog', [
                'handlers' => $this->buildEmailDebugHandlers($container, $config),
            ]);
        }
    }

    /**
     * Teaches `symfony/ux-translator` the one convention this ecosystem holds to: the browser
     * sees a single translation domain, `javascript`.
     *
     * ## Why the socle decides this and not each project
     *
     * The package's default dumps EVERY domain of the catalogue into the JavaScript bundle. On a
     * back-office that is 5 955 keys in `superp`, in every locale it serves — and nobody notices,
     * because nothing breaks. Left to four projects, the line would be written four times and
     * drift three.
     *
     * ⚠️ The domain is a domain of TRANSPORT, not of subject. The other twenty-odd domains answer
     * "what is this label about"; this one answers "who reads it". Mixing the two criteria is
     * exactly why an enum's labels — read by a Twig template, a form's choice_label AND a
     * datatable renderer — have to be moved rather than duplicated.
     *
     * ⚠️ The parameters are set whatever happens, including when `symfony/ux-translator` is not
     * installed: the audit command and `AbstractJsTranslationTestCase` need to know the domain in
     * order to guard a project that has not migrated yet.
     *
     * ⚠️ And nothing is prepended when UxTranslatorBundle is absent. Prepending configuration for
     * an extension the kernel does not have blows the container up at boot, and most applications
     * of this ecosystem take this bundle for its entities alone.
     */
    private function prependJsTranslations(ContainerBuilder $container): void
    {
        $config = $this->resolveJsTranslationsConfig($container);

        $container->setParameter('core.js_translations.domain', $config['domain']);
        $container->setParameter('core.js_translations.dump_directory', $config['dump_directory']);

        $bundles = $container->getParameter('kernel.bundles');

        if (!$config['enabled'] || !\is_array($bundles) || !isset($bundles['UxTranslatorBundle'])) {
            return;
        }

        $container->prependExtensionConfig('ux_translator', [
            'dump_directory' => $config['dump_directory'],
            // A bare string: the package normalises it into an inclusive single-element list.
            'domains' => $config['domain'],
            // ⚠️ The `.d.ts` is dumped on every cache warm-up, so on every deploy, and nothing
            // reads it in production.
            'dump_typescript' => 'prod' !== $container->getParameter('kernel.environment'),
        ]);
    }

    /**
     * Read by hand for the same reason {@see self::resolveConfig()} is: prepend() runs before
     * `%env(...)%` placeholders exist, and running the config tree here would reject a perfectly
     * legal one. load() validates the whole tree properly.
     *
     * @return array{enabled: bool, domain: string, dump_directory: string}
     */
    private function resolveJsTranslationsConfig(ContainerBuilder $container): array
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $merged = [];

        foreach ($configs as $candidate) {
            if (\is_array($candidate) && \is_array($candidate['js_translations'] ?? null)) {
                $merged = [...$merged, ...$candidate['js_translations']];
            }
        }

        $domain = $merged['domain'] ?? null;
        $directory = $merged['dump_directory'] ?? null;

        return [
            'enabled' => false !== ($merged['enabled'] ?? true),
            'domain' => \is_string($domain) && '' !== $domain ? $domain : 'javascript',
            'dump_directory' => \is_string($directory) && '' !== $directory ? $directory : '%kernel.project_dir%/var/translations',
        ];
    }

    /**
     * Normalises the processed configuration into a shape the rest of the class can
     * rely on: Symfony's config layer only guarantees an untyped array.
     *
     * @return CoreConfig
     */
    private function resolveConfig(ContainerBuilder $container): array
    {
        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        // Merged by hand rather than through processConfiguration(): prepend() runs before the
        // container has turned `%env(...)%` strings into placeholders, so validating the whole
        // tree here would reject a perfectly legal `%env(bool:FOO)%` on any typed node. Only
        // the email_debug keys concern this method, and load() validates everything properly.
        $config = [];

        foreach (\is_array($configs) ? $configs : [] as $candidate) {
            if (\is_array($candidate)) {
                $config = [...$config, ...$candidate];
            }
        }

        return [
            'email_debug' => true === ($config['email_debug'] ?? false),
            'email_debug_from' => self::asStringOrNull($config['email_debug_from'] ?? null),
            // The default lives in Configuration too; repeated here because this method no
            // longer runs the tree that would apply it.
            'email_debug_title' => self::asStringOrNull($config['email_debug_title'] ?? 'An error occured'),
            'email_debug_to' => self::asStringOrNull($config['email_debug_to'] ?? null),
        ];
    }

    private static function asStringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildProdHandlers(): array
    {
        return [
            'console' => [
                'channels' => [
                    '!event',
                    '!doctrine',
                ],
                'type' => 'console',
                'process_psr_3_messages' => false,
            ],
            'login' => [
                'channels' => 'security',
                'level' => 'info',
                'path' => '%kernel.logs_dir%/auth.log',
                'type' => 'stream',
            ],
            'main' => [
                'action_level' => 'error',
                'channels' => [
                    '!php',
                ],
                'handler' => 'nested',
                'type' => 'fingers_crossed',
            ],
            'nested' => [
                'level' => 'info',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'type' => 'stream',
            ],
            'php' => [
                'channels' => [
                    'php',
                ],
                'level' => 'warning',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'type' => 'stream',
            ],
        ];
    }

    /**
     * @param CoreConfig $config
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws \InvalidArgumentException if the email debug settings are incomplete
     */
    private function buildEmailDebugHandlers(ContainerBuilder $container, array $config): array
    {
        $from = $config['email_debug_from'];
        $subject = $config['email_debug_title'];
        $to = $config['email_debug_to'];

        if (null === $from || false === filter_var($from, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('Parameter "%s.email_debug_from" must be a valid email address to activate email debug.', $this->getAlias()));
        }

        if (null === $subject || '' === $subject) {
            throw new \InvalidArgumentException(\sprintf('Parameter "%s.email_debug_title" must be configured to activate email debug.', $this->getAlias()));
        }

        if (null === $to || false === filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('Parameter "%s.email_debug_to" must be a valid email address to activate email debug.', $this->getAlias()));
        }

        // Monolog expects a service id here, and MailerHandler only sends an HTML
        // body when its formatter is an HtmlFormatter instance.
        $formatterId = \sprintf('%s.monolog.html_formatter', $this->getAlias());
        $container->register($formatterId, HtmlFormatter::class);

        return [
            'symfony_mailer' => [
                'formatter' => $formatterId,
                'from_email' => $from,
                'level' => 'critical',
                'subject' => $subject,
                'to_email' => $to,
                'type' => 'symfony_mailer',
            ],
            'deduplicated' => [
                'handler' => 'symfony_mailer',
                'type' => 'deduplication',
            ],
        ];
    }
}
