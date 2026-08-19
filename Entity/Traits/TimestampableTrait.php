<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * `created_at` set once at insert, `updated_at` set on every subsequent update.
 *
 * ```php
 * #[ORM\Entity]
 * #[ORM\HasLifecycleCallbacks]          // ← without this, both columns stay NULL
 * class Page
 * {
 *     use TimestampableTrait;
 * }
 * ```
 *
 * Both values are `\DateTimeImmutable`, and neither has a setter: they are stamped by the
 * lifecycle callbacks and nothing else is allowed to rewrite history. A fixture that needs a
 * back-dated row reaches for reflection, deliberately.
 *
 * `createdAt` is mapped `updatable: false`, so even a manual change to the field never reaches
 * an `UPDATE`.
 *
 * Two traps worth knowing before you use it:
 *
 * - **`#[ORM\HasLifecycleCallbacks]` is required on the entity.** Doctrine ignores
 *   `#[ORM\PrePersist]` / `#[ORM\PreUpdate]` without it — silently. The columns are created,
 *   they simply never get a value.
 * - **It cannot be combined with {@see CreatedAtTrait}.** Both declare `$createdAt` and
 *   `onPrePersist()`, so using both in one class is a fatal error, not a merge. Pick one:
 *   this trait when the row's own `updated_at` matters, `CreatedAtTrait` when an audit log
 *   already records who changed what and when.
 *
 * Serialization groups do **not** belong here: an attribute cannot vary from one entity to the
 * next, so a trait carrying `#[Groups]` ends up holding the union of every consumer's groups.
 * Declare them per class in `config/serializer/`.
 */
trait TimestampableTrait
{
    #[ORM\Column(updatable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
