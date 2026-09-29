<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/** ⚠️ Bulk on an owner whose one-to-one child is removed by `orphanRemoval`: refused. */
#[ORM\Entity]
#[ORM\Table(name: 'bulk_orphan_parent')]
#[Purgeable(field: 'createdAt', interval: '-3 months', bulk: true)]
class BulkOrphanParent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: BulkCallbackLog::class, orphanRemoval: true)]
    private ?BulkCallbackLog $attachment = null;

    public function __construct(
        #[ORM\Column]
        private \DateTimeImmutable $createdAt
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
