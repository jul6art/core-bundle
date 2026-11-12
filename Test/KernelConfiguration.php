<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Test;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The configuration an environment WROTE for each extension — `framework`, `security`, `core`… —
 * as its files declare it, `when@<env>` blocks included, read before any compilation.
 *
 * The architecture tests judge the PRODUCTION configuration, but they cannot boot a production
 * kernel: its cache warmers run at boot (the secrets guard rightly refuses the test secrets), and
 * the env vars around it are those of the test process. Building the container without compiling
 * it is enough to read what the files say. The values are raw: an `%env(…)%` stays a placeholder,
 * so a rule can say that a setting is written, not what the server gives it.
 *
 * ```php
 * $framework = KernelConfiguration::of(static::getKernelClass(), 'prod')->extension('framework');
 * ```
 */
final class KernelConfiguration
{
    /** @var array<string, self> one container build per kernel and environment */
    private static array $read = [];

    private function __construct(
        private readonly ContainerBuilder $container,
    ) {
    }

    /**
     * @param string $kernelClass the project's kernel, as `KernelTestCase::getKernelClass()` names it
     */
    public static function of(string $kernelClass, string $environment = 'prod'): self
    {
        return self::$read[$kernelClass.'@'.$environment] ??= new self(self::build($kernelClass, $environment));
    }

    /**
     * Whether a bundle registers this extension — gesdinet, API Platform… — so a rule about it applies.
     */
    public function has(string $extension): bool
    {
        return $this->container->hasExtension($extension);
    }

    /**
     * The extension's configuration, its files merged in loading order.
     *
     * @return array<string, mixed>
     */
    public function extension(string $name): array
    {
        $merged = [];
        foreach ($this->container->getExtensionConfig($name) as $config) {
            $merged = array_replace_recursive($merged, $config);
        }

        // A configuration's first level is named: its nodes.
        $nodes = [];
        foreach ($merged as $node => $value) {
            if (\is_string($node)) {
                $nodes[$node] = $value;
            }
        }

        return $nodes;
    }

    private static function build(string $kernelClass, string $environment): ContainerBuilder
    {
        if (!is_a($kernelClass, Kernel::class, true)) {
            throw new \LogicException(\sprintf('%s is not a Symfony kernel: its configuration cannot be read.', $kernelClass));
        }
        $kernel = new $kernelClass($environment, false);
        // What `Kernel::boot()` does before compiling — and stops there.
        new \ReflectionMethod($kernel, 'initializeBundles')->invoke($kernel);
        $container = new \ReflectionMethod($kernel, 'buildContainer')->invoke($kernel);
        if (!$container instanceof ContainerBuilder) {
            throw new \LogicException(\sprintf('%s::buildContainer() did not answer a ContainerBuilder.', $kernelClass));
        }

        return $container;
    }
}
