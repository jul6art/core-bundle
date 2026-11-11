<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle;

use Jul6Art\CoreBundle\DependencyInjection\Compiler\PerformanceCommandPass;
use Jul6Art\CoreBundle\DependencyInjection\Compiler\PurgeCommandPass;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedTypeRegistrar;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class CoreBundle.
 */
class CoreBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new PurgeCommandPass());
        $container->addCompilerPass(new PerformanceCommandPass());
    }

    /**
     * The `encrypted_string` type gets its encryptor as soon as the kernel boots, and not only at the
     * first request or console command (the registrar's two listeners): code that writes an encrypted
     * column before either — a test that persists in `setUp()`, a kernel booted by hand — found the
     * type without it (« EncryptedStringType has no Encryptor »), depending on which test a process
     * happened to run first.
     */
    #[\Override]
    public function boot(): void
    {
        parent::boot();

        if ($this->container instanceof ContainerInterface && $this->container->has(EncryptedTypeRegistrar::class)) {
            $registrar = $this->container->get(EncryptedTypeRegistrar::class);
            if ($registrar instanceof EncryptedTypeRegistrar) {
                $registrar->register();
            }
        }
    }
}
