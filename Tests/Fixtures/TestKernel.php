<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jul6Art\CoreBundle\Command\PurgeCommand;
use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\CoreBundle\Doctrine\DQL\JsonTextFunction;
use Jul6Art\CoreBundle\Doctrine\SoftDeleteFilter;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedTypeRegistrar;
use Jul6Art\CoreBundle\EntityListener\AbstractEntityListener;
use Jul6Art\CoreBundle\EventListener\AbstractEventListener;
use Jul6Art\CoreBundle\EventListener\SecurityHeaderListener;
use Jul6Art\CoreBundle\Form\Extension\NumberTypeGroupingExtension;
use Jul6Art\CoreBundle\Security\Encryptor;
use Jul6Art\CoreBundle\Security\MathCaptchaService;
use Jul6Art\CoreBundle\Service\CascadeSoftDeleteHelper;
use Jul6Art\CoreBundle\Service\NumberFormatter;
use Jul6Art\CoreBundle\Tests\Fixtures\Listener\ConcreteEntityListener;
use Jul6Art\CoreBundle\Tests\Fixtures\Listener\ConcreteEventListener;
use Jul6Art\CoreBundle\Tests\Fixtures\Manager\WidgetManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Repository\WidgetRepository;
use Jul6Art\CoreBundle\Tests\Fixtures\Security\DashboardVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Security\WidgetVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Service\AwareService;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal application kernel used by the functional tests.
 */
final class TestKernel extends Kernel
{
    public const string ENTITY_LISTENER_ID = 'test.entity_listener';
    public const string EVENT_LISTENER_ID = 'test.event_listener';
    public const string AWARE_SERVICE_ID = 'test.aware_service';

    /**
     * @param array<string, mixed> $coreConfig configuration for the "core" extension
     * @param bool                 $withOrm    registers an in-memory SQLite ORM mapped on the fixtures
     */
    public function __construct(
        string $environment,
        private readonly array $coreConfig = [],
        private readonly bool $withOrm = false,
        private readonly string $uniqueId = 'default',
    ) {
        // Debug mode installs Symfony's error handler and never removes it, which
        // PHPUnit rightly reports as leaking global state. The bundle behaves the
        // same either way, so the tests run the non-debug container.
        parent::__construct($environment, false);
    }

    /**
     * @return iterable<BundleInterface>
     */
    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new MonologBundle();
        yield new TwigBundle();
        yield new CoreBundle();

        if ($this->withOrm) {
            yield new DoctrineBundle();
        }
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->configure(...));
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return $this->buildDir().'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return $this->buildDir().'/log';
    }

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        // Monolog handlers and the security token storage are private; the tests need
        // to reach them to assert on what the bundle actually produced.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                $exposed = [
                    'core.monolog.html_formatter',
                    'doctrine.orm.default_entity_manager',
                    'event_dispatcher',
                    'request_stack',
                    'twig',
                    'security.token_storage',
                    'security.untracked_token_storage',
                    CascadeSoftDeleteHelper::class,
                    DashboardVoter::class,
                    EncryptedTypeRegistrar::class,
                    Encryptor::class,
                    MathCaptchaService::class,
                    NumberFormatter::class,
                    NumberTypeGroupingExtension::class,
                    PurgeCommand::class,
                    SecurityHeaderListener::class,
                    WidgetVoter::class,
                    'lock.factory',
                    'security.authorization_checker',
                    'security.helper',
                ];

                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'monolog.handler.') || \in_array($id, $exposed, true)) {
                        $definition->setPublic(true);
                    }
                }

                foreach ($container->getAliases() as $id => $alias) {
                    if (\in_array($id, $exposed, true)) {
                        $alias->setPublic(true);
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING, 100);
    }

    private function buildDir(): string
    {
        return \sprintf('%s/jul6art-core-bundle-tests/%s/%s', sys_get_temp_dir(), $this->uniqueId, $this->environment);
    }

    private function configure(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'core-bundle-tests',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'translator' => ['default_path' => '%kernel.project_dir%/translations'],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'mailer' => ['dsn' => 'null://null'],
            // flock store: the purge command's concurrency guard needs a real lock factory.
            'lock' => true,
        ]);

        // A real hierarchy, because that is exactly what AbstractVoter::hasRole() must honour
        // and what $token->getRoleNames() would miss.
        $container->loadFromExtension('security', [
            'providers' => ['in_memory' => ['memory' => null]],
            'firewalls' => ['main' => ['security' => false]],
            'role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_EDITOR'], 'ROLE_EDITOR' => ['ROLE_USER']],
        ]);

        // Autowired so the #[Required] setter of AbstractVoter is really exercised: a voter
        // whose Security helper is not injected would fail on an uninitialised property.
        foreach ([WidgetVoter::class, DashboardVoter::class] as $voter) {
            $container->register($voter, $voter)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setPublic(true);
        }

        $container->loadFromExtension('monolog', []);

        // Twig is registered so the bundle's `twig.extension` tags are really consumed: a
        // filter that exists but never reaches a template is the failure this guards against.
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/Tests/Fixtures/views']);

        $container->loadFromExtension('core', $this->coreConfig);

        // Concrete children of the bundle's abstract definitions: this is what proves
        // the `calls:` in Resources/config/services.yaml reference existing services.
        $container->setDefinition(
            self::ENTITY_LISTENER_ID,
            new ChildDefinition(AbstractEntityListener::class)
                ->setClass(ConcreteEntityListener::class)
                ->setPublic(true)
        );

        $container->setDefinition(
            self::EVENT_LISTENER_ID,
            new ChildDefinition(AbstractEventListener::class)
                ->setClass(ConcreteEventListener::class)
                ->setPublic(true)
        );

        if (!$this->withOrm) {
            return;
        }

        // The DBAL type, the DQL function and the SQL filter are registered exactly the way
        // a consuming application registers them — that is what the functional tests assert.
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
                'types' => [
                    EncryptedStringType::NAME => EncryptedStringType::class,
                ],
            ],
            'orm' => [
                'controller_resolver' => ['auto_mapping' => false],
                'mappings' => [
                    'CoreBundleTests' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Jul6Art\CoreBundle\Tests\Fixtures\Entity',
                        'is_bundle' => false,
                    ],
                ],
                'dql' => [
                    'string_functions' => [
                        'JSON_TEXT' => JsonTextFunction::class,
                    ],
                ],
                'filters' => [
                    'soft_delete' => [
                        'class' => SoftDeleteFilter::class,
                        'enabled' => false,
                    ],
                ],
            ],
        ]);

        $container->register(WidgetRepository::class, WidgetRepository::class)
            ->setAutowired(true)
            ->setPublic(true);

        $container->register(WidgetManager::class, WidgetManager::class)
            ->setAutowired(true)
            ->setPublic(true);

        // AwareService needs the ORM, hence its registration alongside Doctrine.
        $container->register(self::AWARE_SERVICE_ID, AwareService::class)
            ->setAutowired(true)
            ->setPublic(true);
    }
}
