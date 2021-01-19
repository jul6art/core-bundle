<?php

namespace Jul6Art\CoreBundle\Service\Traits;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Trait EntityManagerAwareTrait.
 */
trait EntityManagerAwareTrait
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @required
     */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }
}
