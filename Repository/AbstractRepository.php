<?php

namespace Jul6Art\CoreBundle\Repository;

use Jul6Art\CoreBundle\Repository\Interfaces\RepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Mapping\MappingException;

/**
 * Class AbstractRepository
 */
abstract class AbstractRepository extends ServiceEntityRepository implements RepositoryInterface
{
    /**
     * @throws MappingException
     */
    public function clear(): void
    {
        $this->_em->clear();
    }

    /**
     * @param $entity
     * @param bool $flush
     * @throws ORMException
     */
    public function delete($entity, $flush = true): void
    {
        $this->_em->remove($entity);

        if ($flush) {
            $this->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function flush(): void
    {
        $this->_em->flush();
    }

    /**
     * @param $entity
     * @param bool $flush
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function save($entity, $flush = true): void
    {
        $this->_em->persist($entity);

        if ($flush) {
            $this->flush();
        }
    }
}
