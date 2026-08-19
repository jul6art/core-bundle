<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * `created_by` / `updated_by` — who touched the row, as a plain string.
 *
 * ```php
 * #[ORM\Entity]
 * class Invoice
 * {
 *     use AuditableTrait;
 * }
 * ```
 *
 * **Nothing populates these columns on its own.** Unlike the timestamp traits there is no
 * lifecycle callback: the two setters exist so that a service, a listener or a form handler
 * writes the actor explicitly. A trait that guessed the current user would need the security
 * context inside an entity, which is exactly what one does not want.
 *
 * The column is 180 characters, the length Symfony uses for a user identifier, so an email
 * fits. Store an identifier a human can read in a listing — an email or a username — rather
 * than a numeric id nobody can interpret without a join.
 *
 * If the actor is already recorded elsewhere for every mutation (an audit log), these two
 * columns are redundant and tend to stay NULL. Prefer {@see CreatedAtTrait} in that case: a
 * NULL `updated_by` reads as "never modified", which is worse than no column at all.
 */
trait AuditableTrait
{
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $updatedBy = null;

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
