<?php

namespace Jul6Art\CoreBundle\Manager\Interfaces;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Mapping\MappingException;

/**
 * Interface ManagerInterface.
 */
interface ManagerInterface
{
    /**
     * @throws MappingException
     */
    public function clear(): void;

    /**
     * @param $entity
     * @param bool $flush
     *
     * @throws ORMException
     */
    public function delete($entity, $flush = true): void;

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function flush(): void;

    /**
     * @return object[]
     */
    public function getAll(): iterable;

    public function getById(int $id): ?object;

    /**
     * @param $entity
     * @param bool $flush
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function save($entity, $flush = true): void;
}
