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
 * A kernel whose production writes what development does not: trusted hosts and the secrets
 * guard. A secret too short for the guard, so that booting it in `prod` would fail — reading its
 * configuration must not.
 */
final class ConfigurationKernel extends Kernel
{
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
        $environment = $this->environment;
        $loader->load(static function (ContainerBuilder $container) use ($environment): void {
            $container->loadFromExtension('framework', [
                'secret' => 'short',
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'trusted_proxies' => '127.0.0.1,REMOTE_ADDR',
            ]);
            $container->loadFromExtension('core', ['security_headers' => ['enabled' => true]]);

            if ('prod' === $environment) {
                $container->loadFromExtension('framework', ['trusted_hosts' => ['^example\.test$'], 'trusted_proxies' => '%env(TRUSTED_PROXIES)%']);
                $container->loadFromExtension('core', ['secrets_guard' => ['enabled' => true]]);
            }
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
        return sys_get_temp_dir().'/jul6art-core-bundle-tests/configuration/'.$this->environment.'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/jul6art-core-bundle-tests/configuration/log';
    }
}
