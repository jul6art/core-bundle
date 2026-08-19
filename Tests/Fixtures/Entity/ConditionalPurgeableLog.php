<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/**
 * Retention narrowed by an ExpressionLanguage condition: age alone is not enough, the row
 * also has to say it is obsolete.
 */
#[ORM\Entity]
#[ORM\Table(name: 'conditional_purgeable_log')]
#[Purgeable(field: 'createdAt', interval: '-1 month', condition: 'entity.isObsolete()')]
class ConditionalPurgeableLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $obsolete;

    public function __construct(\DateTimeImmutable $createdAt, bool $obsolete)
    {
        $this->createdAt = $createdAt;
        $this->obsolete = $obsolete;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isObsolete(): bool
    {
        return $this->obsolete;
    }
}
