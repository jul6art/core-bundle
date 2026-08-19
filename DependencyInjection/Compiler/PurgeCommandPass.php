<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\DependencyInjection\Compiler;

use Jul6Art\CoreBundle\Command\PurgeCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the purge command when no lock factory is wired.
 *
 * The extension already checks that `symfony/lock` is *installed*, but a project can hold the
 * package without configuring `framework.lock`, in which case `lock.factory` does not exist.
 * Extensions cannot see that — they run before the other bundles have had their say — so the
 * check belongs in a compiler pass.
 *
 * Removing the command is deliberate: a scheduled purge without a lock would let two runs race
 * on the same rows, so no lock means no command rather than an unguarded one.
 */
final class PurgeCommandPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PurgeCommand::class)) {
            return;
        }

        if ($container->has('lock.factory')) {
            return;
        }

        $container->removeDefinition(PurgeCommand::class);
    }
}
