<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Event;

/**
 * Thrown by {@see \Jul6Art\CoreBundle\EntityListener\AbstractEntityListener} when a
 * subscriber aborted one of the `BEFORE_*` events: it unwinds the Doctrine flush so the
 * refused write never reaches the database.
 */
final class PersistenceAbortedException extends \RuntimeException
{
    public function __construct(
        private readonly object $entity,
        ?string $reason = null,
    ) {
        parent::__construct(\sprintf(
            'Persistence of %s aborted%s',
            $entity::class,
            null !== $reason ? ': '.$reason : '',
        ));
    }

    public function getAbortedEntity(): object
    {
        return $this->entity;
    }
}
