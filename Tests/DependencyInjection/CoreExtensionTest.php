<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\DependencyInjection;

use Jul6Art\CoreBundle\DependencyInjection\CoreExtension;
use Jul6Art\CoreBundle\EntityListener\AbstractEntityListener;
use Jul6Art\CoreBundle\EventListener\AbstractEventListener;
use Monolog\Formatter\HtmlFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(CoreExtension::class)]
final class CoreExtensionTest extends TestCase
{
    public function testLoadRegistersTheAbstractEntityListener(): void
    {
        $container = $this->containerBuilder();
        new CoreExtension()->load([], $container);

        self::assertTrue($container->hasDefinition(AbstractEntityListener::class));

        $definition = $container->getDefinition(AbstractEntityListener::class);
        self::assertTrue($definition->isAbstract());

        $calls = array_column($definition->getMethodCalls(), 0);
        self::assertSame(['setRequestStack', 'setTokenStorage', 'setTranslator'], $calls);
    }

    public function testLoadRegistersTheAbstractEventListener(): void
    {
        $container = $this->containerBuilder();
        new CoreExtension()->load([], $container);

        $definition = $container->getDefinition(AbstractEventListener::class);

        self::assertTrue($definition->isAbstract());
        self::assertSame(['setTokenStorage'], array_column($definition->getMethodCalls(), 0));
    }

    /**
     * The entity listener used to receive the "session.flash_bag" service, removed in
     * Symfony 6.0. It must now be wired on the request stack instead.
     */
    public function testTheEntityListenerNoLongerDependsOnTheRemovedFlashBagService(): void
    {
        $container = $this->containerBuilder();
        new CoreExtension()->load([], $container);

        $references = [];
        foreach ($container->getDefinition(AbstractEntityListener::class)->getMethodCalls() as $call) {
            self::assertIsArray($call);
            self::assertIsString($call[0]);
            self::assertIsArray($call[1]);
            self::assertInstanceOf(Reference::class, $call[1][0]);

            $references[$call[0]] = (string) $call[1][0];
        }

        self::assertSame('request_stack', $references['setRequestStack']);
        self::assertNotContains('session.flash_bag', $references);
    }

    /**
     * services.yaml used to declare a Service\Pusher that does not exist in this repo.
     */
    public function testLoadOnlyRegistersClassesThatExist(): void
    {
        $container = $this->containerBuilder();
        new CoreExtension()->load([], $container);

        foreach ($container->getDefinitions() as $id => $definition) {
            if ('service_container' === $id) {
                continue;
            }

            $class = $definition->getClass() ?? $id;
            self::assertTrue(class_exists($class) || interface_exists($class), \sprintf('Service "%s" points at missing class "%s".', $id, $class));
        }
    }

    public function testPrependExposesTheConfigurationAsParameters(): void
    {
        $container = $this->prepend(['email_debug_title' => 'Kaboom']);

        self::assertFalse($container->getParameter('core.email_debug'));
        self::assertNull($container->getParameter('core.email_debug_from'));
        self::assertSame('Kaboom', $container->getParameter('core.email_debug_title'));
        self::assertNull($container->getParameter('core.email_debug_to'));
    }

    public function testPrependAddsTheProdHandlersInProd(): void
    {
        $handlers = $this->monologHandlers($this->prepend([], 'prod'));

        self::assertSame(['console', 'login', 'main', 'nested', 'php'], array_keys($handlers));
        self::assertSame('stream', $handlers['nested']['type']);
    }

    /**
     * "main" carries action_level and handler, which only a fingers_crossed handler
     * reads. It used to be declared as rotating_file, where both were silently
     * ignored and every record was written straight to disk.
     */
    public function testTheMainProdHandlerBuffersUntilAnErrorHappens(): void
    {
        $main = $this->monologHandlers($this->prepend([], 'prod'))['main'];

        self::assertSame('fingers_crossed', $main['type']);
        self::assertSame('error', $main['action_level']);
        self::assertSame('nested', $main['handler']);
    }

    public function testPrependLeavesMonologAloneOutsideProd(): void
    {
        self::assertSame([], $this->monologHandlers($this->prepend([], 'dev')));
    }

    public function testPrependIsSkippedWithoutMonologBundle(): void
    {
        $container = $this->containerBuilder('prod', withMonolog: false);
        $extension = new CoreExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('core', ['email_debug' => true]);

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('monolog'));
        // Parameters are still exposed, they do not depend on Monolog.
        self::assertTrue($container->getParameter('core.email_debug'));
    }

    public function testPrependAddsTheMailHandlersWhenEmailDebugIsEnabled(): void
    {
        $container = $this->prepend($this->validEmailDebugConfig(), 'dev');
        $handlers = $this->monologHandlers($container);

        self::assertArrayHasKey('symfony_mailer', $handlers);
        self::assertArrayHasKey('deduplicated', $handlers);

        self::assertSame('symfony_mailer', $handlers['symfony_mailer']['type']);
        self::assertSame('critical', $handlers['symfony_mailer']['level']);
        self::assertSame('from@example.com', $handlers['symfony_mailer']['from_email']);
        self::assertSame('to@example.com', $handlers['symfony_mailer']['to_email']);
        self::assertSame('Boom', $handlers['symfony_mailer']['subject']);

        self::assertSame('deduplication', $handlers['deduplicated']['type']);
        self::assertSame('symfony_mailer', $handlers['deduplicated']['handler']);
    }

    /**
     * Monolog resolves "formatter" as a service id, so the bundle has to register it:
     * passing the bare class name used to break container compilation.
     */
    public function testTheMailHandlerFormatterIsARegisteredService(): void
    {
        $container = $this->prepend($this->validEmailDebugConfig(), 'dev');
        $formatterId = $this->monologHandlers($container)['symfony_mailer']['formatter'];

        self::assertIsString($formatterId);
        self::assertTrue($container->hasDefinition($formatterId), \sprintf('Formatter "%s" is referenced but never registered.', $formatterId));
        self::assertSame(HtmlFormatter::class, $container->getDefinition($formatterId)->getClass());
    }

    public function testNoFormatterIsRegisteredWhenEmailDebugIsDisabled(): void
    {
        self::assertFalse($this->prepend([], 'dev')->hasDefinition('core.monolog.html_formatter'));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('incompleteEmailDebugConfigs')]
    public function testPrependRejectsIncompleteEmailDebugConfig(array $config, string $expectedParameter): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('Parameter "core.%s"', $expectedParameter));

        $this->prepend($config, 'dev');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function incompleteEmailDebugConfigs(): iterable
    {
        yield 'missing sender' => [
            ['email_debug' => true, 'email_debug_to' => 'to@example.com'],
            'email_debug_from',
        ];

        yield 'malformed sender' => [
            ['email_debug' => true, 'email_debug_from' => 'nope', 'email_debug_to' => 'to@example.com'],
            'email_debug_from',
        ];

        yield 'missing recipient' => [
            ['email_debug' => true, 'email_debug_from' => 'from@example.com'],
            'email_debug_to',
        ];

        yield 'malformed recipient' => [
            ['email_debug' => true, 'email_debug_from' => 'from@example.com', 'email_debug_to' => 'nope'],
            'email_debug_to',
        ];

        yield 'empty subject' => [
            ['email_debug' => true, 'email_debug_from' => 'from@example.com', 'email_debug_to' => 'to@example.com', 'email_debug_title' => ''],
            'email_debug_title',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validEmailDebugConfig(): array
    {
        return [
            'email_debug' => true,
            'email_debug_from' => 'from@example.com',
            'email_debug_to' => 'to@example.com',
            'email_debug_title' => 'Boom',
        ];
    }

    private function containerBuilder(string $environment = 'dev', bool $withMonolog = true): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.bundles' => $withMonolog ? ['MonologBundle' => MonologBundle::class] : [],
            'kernel.environment' => $environment,
        ]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function prepend(array $config, string $environment = 'dev'): ContainerBuilder
    {
        $container = $this->containerBuilder($environment);
        $extension = new CoreExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('core', $config);

        $extension->prepend($container);

        return $container;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function monologHandlers(ContainerBuilder $container): array
    {
        $handlers = [];

        foreach ($container->getExtensionConfig('monolog') as $config) {
            /** @var array<string, array<string, mixed>> $configHandlers */
            $configHandlers = $config['handlers'] ?? [];
            $handlers = [...$handlers, ...$configHandlers];
        }

        ksort($handlers);

        return $handlers;
    }
}
