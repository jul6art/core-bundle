<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * `created_at` only — the lightweight alternative to {@see TimestampableTrait}.
 *
 * ```php
 * #[ORM\Entity]
 * #[ORM\HasLifecycleCallbacks]          // ← without this, the column stays NULL
 * class Contact
 * {
 *     use CreatedAtTrait;
 * }
 * ```
 *
 * Use it when an audit log already records who changed a row and when. In that situation
 * `updated_at` / `updated_by` columns tend to stay NULL forever — nothing populates them — and
 * a NULL `updated_at` reads as "never modified", which is worse than no column at all.
 *
 * The same two traps as {@see TimestampableTrait} apply: `#[ORM\HasLifecycleCallbacks]` is
 * required on the entity, and the two traits are **mutually exclusive** — both declare
 * `$createdAt` and `onPrePersist()`.
 *
 * Serialization groups belong in `config/serializer/`, not here: an attribute cannot vary from
 * one entity to the next.
 */
trait CreatedAtTrait
{
    #[ORM\Column(updatable: false)]
    private \DateTimeImmutable $createdAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
