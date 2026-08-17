<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Manager;

use Doctrine\ORM\OptimisticLockException;
use Jul6Art\CoreBundle\Manager\Interfaces\ManagerInterface;
use Jul6Art\CoreBundle\Repository\AbstractRepository;

/**
 * Class AbstractManager.
 *
 * @template TEntity of object
 *
 * @implements ManagerInterface<TEntity>
 */
abstract class AbstractManager implements ManagerInterface
{
    /**
     * Resolves the repository held by this manager: a "FooManager" is expected to
     * declare a "$fooRepository" property.
     *
     * @return AbstractRepository<TEntity>
     *
     * @throws \ReflectionException if the expected property is not declared
     */
    protected function getAbstractRepository(): AbstractRepository
    {
        $reflection = new \ReflectionClass(static::class);
        $propertyName = str_replace('Manager', 'Repository', lcfirst($reflection->getShortName()));

        $repository = $reflection->getProperty($propertyName)->getValue($this);

        if (!$repository instanceof AbstractRepository) {
            throw new \LogicException(\sprintf('Property "%s::$%s" must hold an instance of "%s", got "%s".', static::class, $propertyName, AbstractRepository::class, get_debug_type($repository)));
        }

        return $repository;
    }

    #[\Override]
    public function clear(): void
    {
        $this->getAbstractRepository()->clear();
    }

    /**
     * @param TEntity $entity
     */
    #[\Override]
    public function delete(object $entity, bool $flush = true): void
    {
        $this->getAbstractRepository()->delete($entity, $flush);
    }

    /**
     * @throws OptimisticLockException
     */
    #[\Override]
    public function flush(): void
    {
        $this->getAbstractRepository()->flush();
    }

    /**
     * @return iterable<int, TEntity>
     */
    #[\Override]
    public function getAll(): iterable
    {
        return $this->getAbstractRepository()->findAll();
    }

    /**
     * @return TEntity|null
     */
    #[\Override]
    public function getById(int $id): ?object
    {
        return $this->getAbstractRepository()->find($id);
    }

    /**
     * @param TEntity $entity
     */
    #[\Override]
    public function save(object $entity, bool $flush = true): void
    {
        $this->getAbstractRepository()->save($entity, $flush);
    }
}
