<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Jul6Art\CoreBundle\Repository\Interfaces\RepositoryInterface;

/**
 * Class AbstractRepository.
 *
 * @template TEntity of object
 *
 * @extends ServiceEntityRepository<TEntity>
 *
 * @implements RepositoryInterface<TEntity>
 */
abstract class AbstractRepository extends ServiceEntityRepository implements RepositoryInterface
{
    #[\Override]
    public function clear(): void
    {
        $this->getEntityManager()->clear();
    }

    /**
     * @param TEntity $entity
     */
    #[\Override]
    public function delete(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->flush();
        }
    }

    /**
     * @throws OptimisticLockException
     */
    #[\Override]
    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * @param TEntity $entity
     */
    #[\Override]
    public function save(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->flush();
        }
    }
}
