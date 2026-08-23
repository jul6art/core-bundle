<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\DependencyInjection\Compiler;

use Jul6Art\CoreBundle\Performance\Command\ClearCommand;
use Jul6Art\CoreBundle\Performance\Command\ExportCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Retire les commandes du profileur quand `framework.lock` n'est pas configuré.
 *
 * `symfony/lock` peut très bien être dans l'arbre de dépendances — en dépendance d'une dépendance
 * — sans que l'application ait configuré `framework.lock` : la classe `LockFactory` existe alors
 * que le service `lock.factory` n'existe pas, et la référence casse la compilation en désignant
 * une commande que le projet n'a jamais demandée.
 *
 * Les deux commandes prennent un verrou parce que purger ou exporter le store pendant qu'une
 * requête y écrit donnerait un fichier tronqué : pas de verrou disponible ⇒ pas de commande,
 * plutôt qu'une commande sans garde.
 *
 * C'est la même règle que {@see PurgeCommandPass}, et c'est délibérément le même mécanisme :
 * une variante — verrou optionnel, référence nullable — ferait deux comportements à retenir là
 * où le bundle n'en a qu'un.
 */
final class PerformanceCommandPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('lock.factory')) {
            return;
        }

        foreach ([ClearCommand::class, ExportCommand::class] as $command) {
            if ($container->hasDefinition($command)) {
                $container->removeDefinition($command);
            }
        }
    }
}
