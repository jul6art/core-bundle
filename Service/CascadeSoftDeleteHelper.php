<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\CoreBundle\Util\Strings;

/**
 * The DQL UPDATE patterns used when a soft-delete on a parent entity has to propagate to
 * its children, possibly across module boundaries:
 *
 *  1. {@see cascadeSoftDelete()} — mark children as soft-deleted with the *same*
 *     timestamp as the parent, so a later restore can tell which rows to resurrect.
 *  2. {@see nullifyForeignKey()} — SET NULL on a nullable FK pointing to a soft-deleted
 *     parent. The child survives, orphaned.
 *  3. {@see cascadeRestore()} — undo (1) on children whose `deletedAt` still matches the
 *     parent's former `deletedAt`. Children soft-deleted independently beforehand stay
 *     deleted.
 *  4. {@see bulkMarkDeletedColumn()} — free a UNIQUE column on every child in one
 *     statement by appending {@see Strings::DELETED_SUFFIX}.
 *  5. {@see bulkRestoreDeletedColumn()} — mirror of (4) on the restore path.
 *
 * Callers pass the child entity as an FQCN string, which keeps the helper decoupled from
 * any particular application namespace: it is meant to be used by per-module event
 * subscribers reacting to cross-module lifecycle events.
 */
class CascadeSoftDeleteHelper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * `Query::execute()` is typed `mixed` because the same method serves SELECT; a DQL
     * UPDATE always yields the affected-row count.
     */
    private static function affectedRows(mixed $result): int
    {
        return \is_int($result) ? $result : 0;
    }

    /**
     * @param class-string $entityClass child entity FQCN
     * @param string       $fkProperty  property name of the FK on the child (e.g. 'owner')
     *
     * @return int number of rows updated
     */
    public function cascadeSoftDelete(
        string $entityClass,
        string $fkProperty,
        object $parent,
        \DateTimeImmutable $deletedAt,
    ): int {
        return self::affectedRows($this->em->createQuery(\sprintf(
            'UPDATE %s e SET e.deletedAt = :ts WHERE e.%s = :parent AND e.deletedAt IS NULL',
            $entityClass,
            $fkProperty,
        ))
            ->setParameter('ts', $deletedAt)
            ->setParameter('parent', $parent)
            ->execute());
    }

    /**
     * @param class-string $entityClass
     *
     * @return int number of rows updated
     */
    public function nullifyForeignKey(
        string $entityClass,
        string $fkProperty,
        object $parent,
    ): int {
        return self::affectedRows($this->em->createQuery(\sprintf(
            'UPDATE %s e SET e.%s = NULL WHERE e.%s = :parent',
            $entityClass,
            $fkProperty,
            $fkProperty,
        ))
            ->setParameter('parent', $parent)
            ->execute());
    }

    /**
     * @param class-string $entityClass
     *
     * @return int number of rows updated
     */
    public function cascadeRestore(
        string $entityClass,
        string $fkProperty,
        object $parent,
        \DateTimeImmutable $parentDeletedAt,
    ): int {
        return self::affectedRows($this->em->createQuery(\sprintf(
            'UPDATE %s e SET e.deletedAt = NULL WHERE e.%s = :parent AND e.deletedAt = :ts',
            $entityClass,
            $fkProperty,
        ))
            ->setParameter('ts', $parentDeletedAt)
            ->setParameter('parent', $parent)
            ->execute());
    }

    /**
     * Appends `<marker><timestamp>` to every row's `$column` where the row points to
     * `$parent` via `$fkProperty` — in a single DQL UPDATE. Frees up UNIQUE columns (slug,
     * email, code, …) across N children without N round-trips.
     *
     * Rows that already carry the marker are skipped, so the operation is idempotent.
     *
     * @param class-string $entityClass
     *
     * @return int number of rows updated
     */
    public function bulkMarkDeletedColumn(
        string $entityClass,
        string $fkProperty,
        object $parent,
        string $column,
        ?int $timestamp = null,
    ): int {
        $timestamp ??= time();

        return self::affectedRows($this->em->createQuery(\sprintf(
            "UPDATE %s e SET e.%s = CONCAT(e.%s, '%s%d') "
            ."WHERE e.%s = :parent AND e.%s IS NOT NULL AND e.%s <> '' "
            ."AND e.%s NOT LIKE '%%%s%%'",
            $entityClass,
            $column,
            $column,
            Strings::DELETED_SUFFIX,
            $timestamp,
            $fkProperty,
            $column,
            $column,
            $column,
            Strings::DELETED_SUFFIX,
        ))
            ->setParameter('parent', $parent)
            ->execute());
    }

    /**
     * Strips the `<marker><digits>` suffix from every row's `$column` where the row points
     * to `$parent` via `$fkProperty` — in a single DQL UPDATE. Mirror of
     * {@see bulkMarkDeletedColumn()} on the restore path.
     *
     * @param class-string $entityClass
     *
     * @return int number of rows updated
     */
    public function bulkRestoreDeletedColumn(
        string $entityClass,
        string $fkProperty,
        object $parent,
        string $column,
    ): int {
        return self::affectedRows($this->em->createQuery(\sprintf(
            "UPDATE %s e SET e.%s = SUBSTRING(e.%s, 1, LOCATE('%s', e.%s) - 1) "
            ."WHERE e.%s = :parent AND e.%s LIKE '%%%s%%'",
            $entityClass,
            $column,
            $column,
            Strings::DELETED_SUFFIX,
            $column,
            $fkProperty,
            $column,
            Strings::DELETED_SUFFIX,
        ))
            ->setParameter('parent', $parent)
            ->execute());
    }
}
