<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A child row held by a DATABASE cascade (`ON DELETE CASCADE`) on a bulk-purged parent: the bulk delete must
 * take it along — the database does it, not the ORM.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bulk_purgeable_entry')]
class BulkPurgeableEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BulkPurgeableLog::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BulkPurgeableLog $log;

    public function __construct(BulkPurgeableLog $log)
    {
        $this->log = $log;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
