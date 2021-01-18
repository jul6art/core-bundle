<?php

namespace Jul6Art\CoreBundle\Repository\Interfaces;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Mapping\MappingException;

/**
 * Interface RepositoryInterface
 */
interface RepositoryInterface
{
    /**
     * @throws MappingException
     */
    public function clear(): void;

    /**
     * @param $entity
     * @param bool $flush
     * @throws ORMException
     */
    public function delete($entity, $flush = true): void;

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function flush(): void;

    /**
     * @param $entity
     * @param bool $flush
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function save($entity, $flush = true): void;
}
