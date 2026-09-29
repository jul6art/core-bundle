<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/** ⚠️ Asks for bulk while its children rely on an ORM cascade: the purge must REFUSE, not skip the cascade. */
#[ORM\Entity]
#[ORM\Table(name: 'bulk_cascading_parent')]
#[Purgeable(field: 'createdAt', interval: '-3 months', bulk: true)]
class BulkCascadingParent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, BulkCascadingChild> */
    #[ORM\OneToMany(targetEntity: BulkCascadingChild::class, mappedBy: 'parent', cascade: ['remove'])]
    private Collection $children;

    public function __construct(#[ORM\Column]
        private \DateTimeImmutable $createdAt)
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
