<?php

namespace Jul6Art\CoreBundle\Repository\Interfaces;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Mapping\MappingException;

/**
 * Interface RepositoryInterface.
 */
interface RepositoryInterface
{
    /**
     * @throws MappingException
     */
    public function clear();

    /**
     * @param $entity
     * @param bool $flush
     *
     * @throws ORMException
     */
    public function delete($entity, $flush = true): void;

    /**
     * @param $id
     * @param null $lockMode
     * @param null $lockVersion
     *
     * @return mixed
     */
    public function find($id, $lockMode = null, $lockVersion = null);

    /**
     * @return mixed
     */
    public function findAll();

    /**
     * @param null $limit
     * @param null $offset
     *
     * @return mixed
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null);

    /**
     * @return mixed
     */
    public function findOneBy(array $criteria, array $orderBy = null);

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function flush(): void;

    /**
     * @param $entity
     * @param bool $flush
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function save($entity, $flush = true): void;
}
