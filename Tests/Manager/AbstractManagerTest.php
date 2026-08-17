<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Manager;

use Jul6Art\CoreBundle\Manager\AbstractManager;
use Jul6Art\CoreBundle\Repository\AbstractRepository;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Jul6Art\CoreBundle\Tests\Fixtures\Manager\MismatchedManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Manager\OrphanManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Manager\WidgetManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Repository\WidgetRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractManager::class)]
final class AbstractManagerTest extends TestCase
{
    public function testItResolvesTheRepositoryNamedAfterTheManager(): void
    {
        $repository = self::createStub(WidgetRepository::class);

        self::assertSame($repository, $this->resolveRepository(new WidgetManager($repository)));
    }

    public function testClearIsDelegated(): void
    {
        $repository = $this->repository();
        $repository->expects(self::once())->method('clear');

        new WidgetManager($repository)->clear();
    }

    public function testFlushIsDelegated(): void
    {
        $repository = $this->repository();
        $repository->expects(self::once())->method('flush');

        new WidgetManager($repository)->flush();
    }

    public function testSaveForwardsTheFlushFlag(): void
    {
        $widget = new Widget();

        $repository = $this->repository();
        $repository->expects(self::once())->method('save')->with($widget, false);

        new WidgetManager($repository)->save($widget, false);
    }

    public function testSaveFlushesByDefault(): void
    {
        $widget = new Widget();

        $repository = $this->repository();
        $repository->expects(self::once())->method('save')->with($widget, true);

        new WidgetManager($repository)->save($widget);
    }

    public function testDeleteForwardsTheFlushFlag(): void
    {
        $widget = new Widget();

        $repository = $this->repository();
        $repository->expects(self::once())->method('delete')->with($widget, false);

        new WidgetManager($repository)->delete($widget, false);
    }

    public function testGetAllReturnsTheRepositoryContent(): void
    {
        $widgets = [new Widget('a'), new Widget('b')];

        $repository = $this->repository();
        $repository->expects(self::once())->method('findAll')->willReturn($widgets);

        self::assertSame($widgets, new WidgetManager($repository)->getAll());
    }

    public function testGetByIdLooksUpTheIdentifier(): void
    {
        $widget = new Widget();

        $repository = $this->repository();
        $repository->expects(self::once())->method('find')->with(7)->willReturn($widget);

        self::assertSame($widget, new WidgetManager($repository)->getById(7));
    }

    /**
     * The lookup is name based, so a property holding anything else must not be
     * returned as if it were a repository.
     */
    public function testItRejectsAPropertyThatIsNotARepository(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('must hold an instance of');

        $this->resolveRepository(new MismatchedManager());
    }

    public function testItFailsWhenTheExpectedPropertyIsMissing(): void
    {
        $this->expectException(\ReflectionException::class);

        $this->resolveRepository(new OrphanManager());
    }

    /**
     * @return WidgetRepository&MockObject
     */
    private function repository(): MockObject
    {
        return $this->createMock(WidgetRepository::class);
    }

    /**
     * @param AbstractManager<Widget> $manager
     *
     * @return AbstractRepository<Widget>
     */
    private function resolveRepository(AbstractManager $manager): AbstractRepository
    {
        $repository = new \ReflectionMethod($manager, 'getAbstractRepository')->invoke($manager);

        self::assertInstanceOf(AbstractRepository::class, $repository);

        return $repository;
    }
}
