<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Manager\Interfaces;

use Doctrine\ORM\OptimisticLockException;

/**
 * Interface ManagerInterface.
 *
 * @template TEntity of object
 */
interface ManagerInterface
{
    public function clear(): void;

    /**
     * @param TEntity $entity
     */
    public function delete(object $entity, bool $flush = true): void;

    /**
     * @throws OptimisticLockException
     */
    public function flush(): void;

    /**
     * @return iterable<int, TEntity>
     */
    public function getAll(): iterable;

    /**
     * @return TEntity|null
     */
    public function getById(int $id): ?object;

    /**
     * @param TEntity $entity
     */
    public function save(object $entity, bool $flush = true): void;
}
