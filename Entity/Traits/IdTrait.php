<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait IdTrait.
 */
trait IdTrait
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
