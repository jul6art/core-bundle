<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL filter that excludes soft-deleted entities by adding
 * `AND deleted_at IS NULL` to every query targeting an entity that declares a
 * `deletedAt` field. Entities without the field are left untouched — emitting the
 * constraint for them would reference a column that does not exist.
 *
 * Register and enable it from the application:
 *
 * ```yaml
 * doctrine:
 *     orm:
 *         filters:
 *             soft_delete:
 *                 class: Jul6Art\CoreBundle\Doctrine\SoftDeleteFilter
 *                 enabled: true
 * ```
 */
final class SoftDeleteFilter extends SQLFilter
{
    /** Entities that declare this field are filtered; the others are left untouched. */
    public const string FIELD = 'deletedAt';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField(self::FIELD)) {
            return '';
        }

        // The column name comes from the mapping, not from a hard-coded `deleted_at`: the
        // default Doctrine naming strategy leaves the property name as-is (`deletedAt`),
        // and only an underscore strategy produces `deleted_at`. Hard-coding one of the two
        // silently breaks the filter for applications using the other.
        return \sprintf('%s.%s IS NULL', $targetTableAlias, $targetEntity->getColumnName(self::FIELD));
    }
}
