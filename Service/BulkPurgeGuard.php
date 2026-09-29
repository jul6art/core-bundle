<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service;

use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/**
 * Decides whether an entity may be purged in bulk — a `DELETE ... WHERE id IN (...)` that never hydrates a row.
 *
 * ⚠️ **Bulk skips the ORM**, so it must never be used where the ORM does something on remove: a `cascade: remove`
 * or an `orphanRemoval` would leave children behind, a remove callback or entity listener (deleting a file,
 * writing a trail) would never run. Those entities are REFUSED, with the reason — never silently deleted around.
 * A database `ON DELETE CASCADE` is not the ORM's business: the database applies it to the bulk delete as to any.
 */
final class BulkPurgeGuard
{
    private const array REMOVE_EVENTS = [Events::preRemove, Events::postRemove];

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @throws \LogicException naming why bulk is refused
     */
    public static function assertSafe(ClassMetadata $metadata, Purgeable $purgeable): void
    {
        $class = $metadata->getName();

        if ('' !== $purgeable->condition) {
            throw new \LogicException(\sprintf('Bulk purge refused for %s: it has a condition, which is evaluated on hydrated rows — drop "bulk" or the condition.', $class));
        }

        foreach ($metadata->getAssociationMappings() as $name => $association) {
            // Orphan removal first: Doctrine turns it into a cascade too, and the message must name the real cause.
            if ($association->orphanRemoval) {
                throw new \LogicException(\sprintf('Bulk purge refused for %s: it removes orphans on "%s", which a bulk delete would skip.', $class, $name));
            }

            if ($association->isCascadeRemove()) {
                throw new \LogicException(\sprintf('Bulk purge refused for %s: it cascades the removal of "%s" through the ORM, which a bulk delete would skip.', $class, $name));
            }
        }

        foreach (self::REMOVE_EVENTS as $event) {
            if ($metadata->hasLifecycleCallbacks($event)) {
                throw new \LogicException(\sprintf('Bulk purge refused for %s: it has a %s lifecycle callback, which a bulk delete would never call.', $class, $event));
            }

            if ([] !== ($metadata->entityListeners[$event] ?? [])) {
                throw new \LogicException(\sprintf('Bulk purge refused for %s: it has a %s entity listener, which a bulk delete would never call.', $class, $event));
            }
        }
    }
}
