<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/**
 * A retention read from a CONTAINER PARAMETER, itself fed by an environment variable: the
 * interval an application wants to change without a migration (an audit trail's retention).
 */
#[ORM\Entity]
#[ORM\Table(name: 'parametrised_purgeable_log')]
#[Purgeable(field: 'createdAt', interval: '%core_test.retention%')]
class ParametrisedPurgeableLog
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
