<?php

namespace Jul6Art\CoreBundle\Manager;

use Jul6Art\CoreBundle\Manager\Interfaces\ManagerInterface;
use Jul6Art\CoreBundle\Repository\AbstractRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Mapping\MappingException;

/**
 * Class AbstractManager
 */
abstract class AbstractManager implements ManagerInterface
{
    /**
     * @return AbstractRepository
     */
    protected function getAbstractRepository(): AbstractRepository
    {
        $className = explode('\\', static::class);
        $repositoryName = str_replace('Manager', 'Repository', lcfirst(array_pop($className)));

        return $this->$repositoryName;
    }

    /**
     * @throws MappingException
     */
    public function clear(): void
    {
        $this->getAbstractRepository()->clear();
    }

    /**
     * @return object
     */
    abstract public function create(): object;

    /**
     * @param $entity
     * @param bool $flush
     * @throws ORMException
     */
    public function delete($entity, $flush = true): void
    {
        $this->getAbstractRepository()->delete($entity, $flush);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function flush(): void
    {
        $this->getAbstractRepository()->flush();
    }

    /**
     * @return object[]
     */
    public function getAll(): iterable
    {
        return $this->getAbstractRepository()->findAll();
    }

    /**
     * @param int $id
     * @return object|null
     */
    public function getById(int $id): ?object
    {
        return $this->getAbstractRepository()->find($id);
    }

    /**
     * @param $entity
     * @param bool $flush
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function save($entity, $flush = true): void
    {
        $this->getAbstractRepository()->save($entity, $flush);
    }
}
