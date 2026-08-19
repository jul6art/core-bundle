<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Util\Strings;

/**
 * Soft delete: the row stays, `deleted_at` is stamped.
 *
 * ```php
 * #[ORM\Entity]
 * class Contact
 * {
 *     use SoftDeletableTrait;
 * }
 * ```
 *
 * No lifecycle callback here, so no `#[ORM\HasLifecycleCallbacks]` needed — the entity is
 * marked by an explicit `softDelete()` call.
 *
 * **Hiding the row is the application's job.** This trait stores a date, nothing more. Register
 * {@see \Jul6Art\CoreBundle\Doctrine\SoftDeleteFilter} to have Doctrine exclude the rows from
 * every query, and use {@see \Jul6Art\CoreBundle\Service\CascadeSoftDeleteHelper} to carry the
 * deletion down to children. Without one of the two, a soft-deleted row keeps showing up.
 *
 * ## UNIQUE columns
 *
 * Soft-deleting a row keeps its values, so a UNIQUE column blocks the next row that wants the
 * same value — a user deleted then re-created with the same email fails to insert.
 * {@see Strings::markDeleted()} frees the original value and {@see Strings::restoreDeleted()}
 * gives it back:
 *
 * ```php
 * public function softDeleteUser(User $user): void
 * {
 *     $user->setEmail(Strings::markDeleted($user->getEmail()));   // ada@x.test_DELETED_1755...
 *     $user->softDelete();
 * }
 * ```
 *
 * Those two are **not** on this trait, deliberately: PHP 8.5 deprecates calling a static trait
 * method on the trait itself, and they are string operations on a convention rather than entity
 * behaviour. They sit next to the constant that defines the convention.
 *
 * Serialization groups belong in `config/serializer/`, not here: an attribute cannot vary from
 * one entity to the next. `deletedAt` is usually what a data table needs to grey a row out and
 * to decide whether it offers restore or delete, so forgetting its group is a visible bug.
 */
trait SoftDeletableTrait
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    public function restore(): static
    {
        $this->deletedAt = null;

        return $this;
    }
}
