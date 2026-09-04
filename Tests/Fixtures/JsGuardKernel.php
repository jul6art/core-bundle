<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures;

use Jul6Art\CoreBundle\CoreBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The smallest application an AbstractJsTranslationTestCase needs: a translator, the enabled
 * locales, and `framework.test` so KernelTestCase can reach the container.
 */
final class JsGuardKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', false);
    }

    /**
     * @return iterable<BundleInterface>
     */
    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new CoreBundle();
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'core-bundle-tests',
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'test' => true,
                'enabled_locales' => ['en', 'fr'],
                'translator' => [
                    'default_path' => '%kernel.project_dir%/Tests/Fixtures/translations',
                ],
            ]);

            $container->loadFromExtension('core', []);
        });
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/jul6art-core-bundle-tests/js-guard/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/jul6art-core-bundle-tests/js-guard/log';
    }
}
