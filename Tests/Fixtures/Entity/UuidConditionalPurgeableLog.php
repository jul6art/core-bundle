<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A conditional policy on an entity whose identifier is an OBJECT (a Uuid), not an int: the
 * purge cursor has to step over the rows the condition keeps without reading them as a scalar.
 */
#[ORM\Entity]
#[ORM\Table(name: 'uuid_conditional_purgeable_log')]
#[Purgeable(field: 'createdAt', interval: '-1 month', condition: 'entity.isObsolete()')]
class UuidConditionalPurgeableLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $obsolete;

    public function __construct(\DateTimeImmutable $createdAt, bool $obsolete)
    {
        $this->id = Uuid::v7();
        $this->createdAt = $createdAt;
        $this->obsolete = $obsolete;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function isObsolete(): bool
    {
        return $this->obsolete;
    }
}
