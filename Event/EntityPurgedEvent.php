<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Event;

/**
 * Dispatched once per row removed by {@see \Jul6Art\CoreBundle\Command\PurgeCommand}.
 *
 * This is how a purge reaches an audit trail without the command knowing that one exists:
 * subscribe to {@see self::NAME} and write whatever the application needs.
 *
 * It carries **scalars only**. By the time it is dispatched the row has been removed *and*
 * flushed — the entity is detached, so handing it over would invite lazy-loading failures on
 * something that no longer exists.
 */
final class EntityPurgedEvent extends AbstractEvent
{
    public const string NAME = 'core.entity.purged';

    public function __construct(
        /** @var class-string */
        private readonly string $entityClass,
        private readonly string $entityShortName,
        private readonly int|string|null $entityId,
        private readonly ?int $organizationId,
        private readonly string $interval,
        private readonly string $condition = '',
    ) {
        parent::__construct();
    }

    /** @return class-string */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityShortName(): string
    {
        return $this->entityShortName;
    }

    public function getEntityId(): int|string|null
    {
        return $this->entityId;
    }

    /** Resolved through `getOrganizationId()` when the entity exposes one, `null` otherwise. */
    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }

    /** The `#[Purgeable]` interval that caught this row — the reason it was removed. */
    public function getInterval(): string
    {
        return $this->interval;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }
}
