<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Repository\Interfaces;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectRepository;

/**
 * Interface RepositoryInterface.
 *
 * find(), findAll(), findBy() and findOneBy() are inherited from ObjectRepository
 * rather than redeclared, so their signatures cannot drift from Doctrine's.
 *
 * @template TEntity of object
 *
 * @extends ObjectRepository<TEntity>
 */
interface RepositoryInterface extends ObjectRepository
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
     * @param TEntity $entity
     */
    public function save(object $entity, bool $flush = true): void;
}
