<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/** A high-volume log purged in bulk: no ORM cascade, no remove callback — the only case bulk accepts. */
#[ORM\Entity]
#[ORM\Table(name: 'bulk_purgeable_log')]
#[Purgeable(field: 'createdAt', interval: '-3 months', bulk: true)]
class BulkPurgeableLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?int $organizationId;

    public function __construct(\DateTimeImmutable $createdAt, ?int $organizationId = null)
    {
        $this->createdAt = $createdAt;
        $this->organizationId = $organizationId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }
}
