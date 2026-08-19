<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/**
 * Two retention policies on the same entity — the reason `#[Purgeable]` is
 * `IS_REPEATABLE`: a short delay for rows already soft-deleted, a long one for everything
 * else.
 */
#[ORM\Entity]
#[ORM\Table(name: 'repeatable_purgeable_log')]
#[Purgeable(field: 'createdAt', interval: '-2 years')]
#[Purgeable(field: 'deletedAt', interval: '-1 week')]
class RepeatablePurgeableLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt;

    public function __construct(\DateTimeImmutable $createdAt, ?\DateTimeImmutable $deletedAt = null)
    {
        $this->createdAt = $createdAt;
        $this->deletedAt = $deletedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
