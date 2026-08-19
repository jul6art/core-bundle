<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Attribute;

/**
 * Declares a retention policy on an entity, applied by
 * {@see \Jul6Art\CoreBundle\Command\PurgeCommand}.
 *
 * Repeatable, because one entity often needs two delays — a short one for rows already
 * soft-deleted, a long one for the rest:
 *
 * ```php
 * #[Purgeable(field: 'createdAt', interval: '-3 months')]
 * #[Purgeable(field: 'deletedAt', interval: '-1 week')]
 * class AuditLog { … }
 * ```
 *
 * The optional condition is an ExpressionLanguage expression evaluated with the row under
 * `entity`, for policies that age alone cannot express:
 *
 * ```php
 * #[Purgeable(field: 'deletedAt', interval: '-1 year', condition: 'entity.isDeleted()')]
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Purgeable
{
    public function __construct(
        /** Datetime field carrying the row's age (e.g. 'createdAt', 'deletedAt'). */
        public readonly string $field,
        /** Relative interval, as `DateTimeImmutable` understands it (e.g. '-3 months'). */
        public readonly string $interval,
        /** Optional ExpressionLanguage condition; the row is exposed as `entity`. */
        public readonly string $condition = '',
    ) {
    }
}
