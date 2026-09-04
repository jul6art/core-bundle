<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Jul6Art\CoreBundle\Tests\Fixtures\RestoresExceptionHandlerTrait;
use Jul6Art\CoreBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractFunctionalTestCase extends TestCase
{
    use RestoresExceptionHandlerTrait;

    private ?TestKernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;

        self::restoreSymfonyExceptionHandler();

        parent::tearDown();
    }

    /**
     * Boots a kernel and returns its container.
     *
     * The build directory is keyed on the configuration so that two scenarios never
     * share a compiled container, while identical scenarios still reuse the cache.
     *
     * @param array<string, mixed> $coreConfig
     * @param array<string, mixed> $uxTranslatorConfig
     */
    final protected function boot(
        string $environment = 'test',
        array $coreConfig = [],
        bool $withOrm = false,
        bool $withUxTranslator = false,
        array $uxTranslatorConfig = [],
    ): ContainerInterface {
        $uniqueId = substr(md5(serialize([$coreConfig, $withOrm, $withUxTranslator, $uxTranslatorConfig])), 0, 12);

        $this->kernel = new TestKernel($environment, $coreConfig, $withOrm, $uniqueId, $withUxTranslator, $uxTranslatorConfig);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }
}
