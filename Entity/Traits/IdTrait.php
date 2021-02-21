<?php

namespace Jul6Art\CoreBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait IdTrait.
 */
trait IdTrait
{
    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }
}
