<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\DependencyInjection;

use Jul6Art\CoreBundle\DependencyInjection\Configuration;
use Jul6Art\CoreBundle\DependencyInjection\CoreExtension;
use Jul6Art\CoreBundle\Logger\QueryStringRedactingProcessor;
use Monolog\Processor\ProcessorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * The bundle only SUGGESTS Monolog: an application or a bundle without it must still boot.
 *
 * ⚠️ Monolog is in this repository's require-dev, so every other test has it. That is how the 3.1.0
 * regression stayed green here — the configuration tree read a constant of the processor, which loads
 * its Monolog interface — while it broke the kernel of every consumer without Monolog (audit-bundle,
 * auth-bundle and push-bundle, found by their CI on 2026-10-01). Each case therefore runs in its own
 * process, where the autoloader refuses the `Monolog\` namespace.
 */
#[CoversClass(Configuration::class)]
#[CoversClass(CoreExtension::class)]
final class WithoutMonologTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testTheConfigurationTreeBuildsWithoutMonolog(): void
    {
        self::hideMonolog();

        $config = new Processor()->processConfiguration(new Configuration(), []);

        self::assertSame(['enabled' => true, 'parameters' => ['_hash', 'token', '_token', 'q', 'search']], $config['log_redaction']);
    }

    #[RunInSeparateProcess]
    public function testTheExtensionLoadsWithoutMonologAndRegistersNoProcessor(): void
    {
        self::hideMonolog();

        $container = new ContainerBuilder(new ParameterBag(['kernel.bundles' => [], 'kernel.environment' => 'prod']));
        new CoreExtension()->load([], $container);

        self::assertFalse($container->hasDefinition(QueryStringRedactingProcessor::class), 'Without Monolog there is no log to protect.');
    }

    private static function hideMonolog(): void
    {
        foreach (spl_autoload_functions() as $loader) {
            spl_autoload_unregister($loader);
            spl_autoload_register(static function (string $class) use ($loader): void {
                if (!str_starts_with($class, 'Monolog\\')) {
                    $loader($class);
                }
            });
        }

        // The premise: if Monolog were still reachable, both cases would pass for the wrong reason.
        self::assertFalse(interface_exists(ProcessorInterface::class), 'Monolog is still reachable: this case proves nothing.');
    }
}
