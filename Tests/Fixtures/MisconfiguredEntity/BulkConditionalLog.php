<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/** ⚠️ Bulk with a condition: a condition is evaluated per HYDRATED row, which bulk never does — refused. */
#[ORM\Entity]
#[ORM\Table(name: 'bulk_conditional_log')]
#[Purgeable(field: 'createdAt', interval: '-3 months', condition: 'entity.getId() > 0', bulk: true)]
class BulkConditionalLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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
