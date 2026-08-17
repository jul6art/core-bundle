<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait EntityManagerAwareTrait.
 */
trait EntityManagerAwareTrait
{
    protected EntityManagerInterface $entityManager;

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }
}
