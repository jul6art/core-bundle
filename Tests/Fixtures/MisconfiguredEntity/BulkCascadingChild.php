<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bulk_cascading_child')]
class BulkCascadingChild
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: BulkCascadingParent::class, inversedBy: 'children')]
        #[ORM\JoinColumn(nullable: false)]
        private BulkCascadingParent $parent
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
