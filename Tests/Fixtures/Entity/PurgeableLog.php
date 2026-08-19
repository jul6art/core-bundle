<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/**
 * The ordinary retention case: one policy on one date field. Also carries the
 * `getOrganizationId()` the command looks for by `method_exists()`, so the purge event can
 * name the tenant a purged row belonged to.
 */
#[ORM\Entity]
#[ORM\Table(name: 'purgeable_log')]
#[Purgeable(field: 'createdAt', interval: '-3 months')]
class PurgeableLog
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }
}
