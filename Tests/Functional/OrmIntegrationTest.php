<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Jul6Art\CoreBundle\Tests\Fixtures\Manager\WidgetManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Repository\WidgetRepository;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exercises AbstractRepository and AbstractManager against a real in-memory SQLite
 * database. This is what proves the ORM 3 port works: EntityRepository dropped the
 * $_em property the bundle used to rely on, and the identifier mapping moved to
 * attributes.
 */
#[CoversNothing]
final class OrmIntegrationTest extends AbstractFunctionalTestCase
{
    private ContainerInterface $container;
    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->boot('test', [], withOrm: true);

        $entityManager = $this->container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(Widget::class),
        ]);
    }

    public function testTheIdTraitMappingProducesAnAutoIncrementedPrimaryKey(): void
    {
        $widget = new Widget('first');
        self::assertNull($widget->getId());

        $this->repository()->save($widget);

        self::assertNotNull($widget->getId());
        self::assertSame(1, $widget->getId());
    }

    public function testSaveWritesThroughAndFindReadsBack(): void
    {
        $widget = new Widget('readable');
        $this->repository()->save($widget);

        $this->entityManager->clear();

        $found = $this->repository()->find($widget->getId());

        self::assertInstanceOf(Widget::class, $found);
        self::assertSame('readable', $found->getName());
    }

    /**
     * The second argument must really defer the write, not just skip a no-op.
     */
    public function testSaveWithoutFlushDefersTheWrite(): void
    {
        $repository = $this->repository();

        $repository->save(new Widget('deferred'), false);

        self::assertSame(0, $this->countRows());

        $repository->flush();

        self::assertSame(1, $this->countRows());
    }

    public function testDeleteRemovesTheRow(): void
    {
        $repository = $this->repository();

        $widget = new Widget('doomed');
        $repository->save($widget);
        self::assertSame(1, $this->countRows());

        $repository->delete($widget);

        self::assertSame(0, $this->countRows());
    }

    public function testDeleteWithoutFlushDefersTheRemoval(): void
    {
        $repository = $this->repository();

        $widget = new Widget('doomed');
        $repository->save($widget);

        $repository->delete($widget, false);
        self::assertSame(1, $this->countRows());

        $repository->flush();
        self::assertSame(0, $this->countRows());
    }

    public function testFindAllReturnsEverySavedEntity(): void
    {
        $repository = $this->repository();
        $repository->save(new Widget('a'), false);
        $repository->save(new Widget('b'), false);
        $repository->flush();

        $names = array_map(static fn (Widget $w): string => $w->getName(), $repository->findAll());
        sort($names);

        self::assertSame(['a', 'b'], $names);
    }

    public function testClearDetachesManagedEntities(): void
    {
        $repository = $this->repository();

        $widget = new Widget('managed');
        $repository->save($widget);

        self::assertTrue($this->entityManager->contains($widget));

        $repository->clear();

        self::assertFalse($this->entityManager->contains($widget));
    }

    public function testTheManagerDelegatesToTheRepositoryForReal(): void
    {
        $manager = $this->manager();

        $widget = new Widget('through-manager');
        $manager->save($widget);

        self::assertSame(1, $this->countRows());
        self::assertNotNull($widget->getId());

        $found = $manager->getById($widget->getId());
        self::assertInstanceOf(Widget::class, $found);
        self::assertSame('through-manager', $found->getName());

        self::assertCount(1, [...$manager->getAll()]);

        $manager->delete($widget);
        self::assertSame(0, $this->countRows());
    }

    public function testTheManagerResolvesTheContainerWiredRepository(): void
    {
        self::assertSame(
            $this->container->get(WidgetRepository::class),
            new \ReflectionMethod($this->manager(), 'getAbstractRepository')->invoke($this->manager())
        );
    }

    private function repository(): WidgetRepository
    {
        $repository = $this->container->get(WidgetRepository::class);

        self::assertInstanceOf(WidgetRepository::class, $repository);

        return $repository;
    }

    private function manager(): WidgetManager
    {
        $manager = $this->container->get(WidgetManager::class);

        self::assertInstanceOf(WidgetManager::class, $manager);

        return $manager;
    }

    /**
     * Counts through raw SQL so the ORM identity map cannot mask a missing write.
     */
    private function countRows(): int
    {
        $count = $this->entityManager->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM widget')
            ->fetchOne();

        self::assertIsNumeric($count);

        return (int) $count;
    }
}
