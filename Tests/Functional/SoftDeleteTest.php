<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\CoreBundle\Service\CascadeSoftDeleteHelper;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Gadget;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The soft-delete bricks against a real database. Every assertion reads back through raw
 * SQL or after an explicit `clear()`, because DQL UPDATE statements bypass the identity
 * map — an ORM-level read would happily return the stale in-memory state.
 */
#[CoversNothing]
final class SoftDeleteTest extends AbstractFunctionalTestCase
{
    private EntityManagerInterface $entityManager;
    private CascadeSoftDeleteHelper $helper;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->boot('test', [], withOrm: true);

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $helper = $container->get(CascadeSoftDeleteHelper::class);
        self::assertInstanceOf(CascadeSoftDeleteHelper::class, $helper);
        $this->helper = $helper;

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(Widget::class),
            $this->entityManager->getClassMetadata(Gadget::class),
        ]);
    }

    public function testCascadeSoftDeleteMarksTheChildrenOfThatParentOnly(): void
    {
        $parent = $this->widget('kept');
        $other = $this->widget('other');
        $mine = $this->gadget($parent);
        $theirs = $this->gadget($other);

        $deletedAt = new \DateTimeImmutable('2026-08-19 10:00:00');
        $updated = $this->helper->cascadeSoftDelete(Gadget::class, 'widget', $parent, $deletedAt);

        self::assertSame(1, $updated);
        self::assertSame('2026-08-19 10:00:00', $this->deletedAtOf($mine));
        self::assertNull($this->deletedAtOf($theirs));
    }

    /**
     * A child soft-deleted on its own keeps its own timestamp: that is what lets
     * cascadeRestore() tell "deleted with the parent" from "deleted before the parent".
     */
    public function testCascadeSoftDeleteLeavesAlreadyDeletedChildrenUntouched(): void
    {
        $parent = $this->widget();
        $earlier = $this->gadget($parent, deletedAt: new \DateTimeImmutable('2026-01-01 00:00:00'));

        $updated = $this->helper->cascadeSoftDelete(Gadget::class, 'widget', $parent, new \DateTimeImmutable('2026-08-19 10:00:00'));

        self::assertSame(0, $updated);
        self::assertSame('2026-01-01 00:00:00', $this->deletedAtOf($earlier));
    }

    public function testNullifyForeignKeyOrphansTheChildrenWithoutDeletingThem(): void
    {
        $parent = $this->widget();
        $child = $this->gadget($parent);

        $updated = $this->helper->nullifyForeignKey(Gadget::class, 'widget', $parent);

        self::assertSame(1, $updated);
        self::assertNull($this->columnOf($child, 'widget_id'));
        self::assertSame(1, $this->countGadgets());
    }

    public function testCascadeRestoreOnlyResurrectsChildrenDeletedWithTheParent(): void
    {
        $parent = $this->widget();
        $deletedAt = new \DateTimeImmutable('2026-08-19 10:00:00');
        $withParent = $this->gadget($parent, deletedAt: $deletedAt);
        $onItsOwn = $this->gadget($parent, deletedAt: new \DateTimeImmutable('2026-01-01 00:00:00'));

        $updated = $this->helper->cascadeRestore(Gadget::class, 'widget', $parent, $deletedAt);

        self::assertSame(1, $updated);
        self::assertNull($this->deletedAtOf($withParent));
        self::assertSame('2026-01-01 00:00:00', $this->deletedAtOf($onItsOwn));
    }

    public function testBulkMarkDeletedColumnFreesTheUniqueValue(): void
    {
        $parent = $this->widget();
        $child = $this->gadget($parent, code: 'ACME');

        $updated = $this->helper->bulkMarkDeletedColumn(Gadget::class, 'widget', $parent, 'code', 1750000000);

        self::assertSame(1, $updated);
        self::assertSame('ACME_DELETED_1750000000', $this->columnOf($child, 'code'));
    }

    /** Re-running the mark must not stack a second marker. */
    public function testBulkMarkDeletedColumnIsIdempotent(): void
    {
        $parent = $this->widget();
        $child = $this->gadget($parent, code: 'ACME');

        $this->helper->bulkMarkDeletedColumn(Gadget::class, 'widget', $parent, 'code', 1750000000);
        $second = $this->helper->bulkMarkDeletedColumn(Gadget::class, 'widget', $parent, 'code', 1750000001);

        self::assertSame(0, $second);
        self::assertSame('ACME_DELETED_1750000000', $this->columnOf($child, 'code'));
    }

    public function testBulkMarkDeletedColumnSkipsNullAndEmptyValues(): void
    {
        $parent = $this->widget();
        $nullCode = $this->gadget($parent);
        $emptyCode = $this->gadget($parent, code: '');

        $updated = $this->helper->bulkMarkDeletedColumn(Gadget::class, 'widget', $parent, 'code', 1750000000);

        self::assertSame(0, $updated);
        self::assertNull($this->columnOf($nullCode, 'code'));
        self::assertSame('', $this->columnOf($emptyCode, 'code'));
    }

    public function testBulkRestoreDeletedColumnStripsTheMarker(): void
    {
        $parent = $this->widget();
        $child = $this->gadget($parent, code: 'ACME');

        $this->helper->bulkMarkDeletedColumn(Gadget::class, 'widget', $parent, 'code', 1750000000);
        $restored = $this->helper->bulkRestoreDeletedColumn(Gadget::class, 'widget', $parent, 'code');

        self::assertSame(1, $restored);
        self::assertSame('ACME', $this->columnOf($child, 'code'));
    }

    public function testBulkRestoreDeletedColumnIgnoresUnmarkedValues(): void
    {
        $parent = $this->widget();
        $child = $this->gadget($parent, code: 'ACME');

        self::assertSame(0, $this->helper->bulkRestoreDeletedColumn(Gadget::class, 'widget', $parent, 'code'));
        self::assertSame('ACME', $this->columnOf($child, 'code'));
    }

    /**
     * The filter is what makes soft-deleted rows disappear from *every* query without
     * touching a single repository method.
     */
    public function testTheSqlFilterHidesSoftDeletedRowsWhenEnabled(): void
    {
        $parent = $this->widget();
        $this->gadget($parent);
        $this->gadget($parent, deletedAt: new \DateTimeImmutable('2026-08-19 10:00:00'));

        $this->entityManager->clear();
        self::assertCount(2, $this->allGadgets());

        $this->entityManager->getFilters()->enable('soft_delete');
        $this->entityManager->clear();

        self::assertCount(1, $this->allGadgets());
    }

    public function testTheSqlFilterLeavesEntitiesWithoutADeletedAtFieldAlone(): void
    {
        $this->widget('visible');

        $this->entityManager->getFilters()->enable('soft_delete');
        $this->entityManager->clear();

        /** @var list<Widget> $widgets */
        $widgets = $this->entityManager->createQuery(\sprintf('SELECT w FROM %s w', Widget::class))->getResult();

        self::assertCount(1, $widgets);
    }

    private function widget(string $name = 'parent'): Widget
    {
        $widget = new Widget($name);
        $this->entityManager->persist($widget);
        $this->entityManager->flush();

        return $widget;
    }

    private function gadget(Widget $parent, ?\DateTimeImmutable $deletedAt = null, ?string $code = null): Gadget
    {
        $gadget = new Gadget()
            ->setWidget($parent)
            ->setDeletedAt($deletedAt)
            ->setCode($code);

        $this->entityManager->persist($gadget);
        $this->entityManager->flush();

        return $gadget;
    }

    private function deletedAtOf(Gadget $gadget): ?string
    {
        return $this->columnOf($gadget, 'deletedAt');
    }

    private function columnOf(Gadget $gadget, string $column): ?string
    {
        $value = $this->entityManager->getConnection()
            ->executeQuery(\sprintf('SELECT %s FROM gadget WHERE id = ?', $column), [$gadget->getId()])
            ->fetchOne();

        if (null === $value || false === $value) {
            return null;
        }

        self::assertIsScalar($value);

        return (string) $value;
    }

    /**
     * Read through DQL rather than getRepository(): the fixture repository implements
     * ServiceEntityRepositoryInterface and is not tagged in this minimal kernel.
     *
     * @return list<Gadget>
     */
    private function allGadgets(): array
    {
        /** @var list<Gadget> $gadgets */
        $gadgets = $this->entityManager->createQuery(\sprintf('SELECT g FROM %s g', Gadget::class))->getResult();

        return $gadgets;
    }

    private function countGadgets(): int
    {
        $count = $this->entityManager->getConnection()->executeQuery('SELECT COUNT(*) FROM gadget')->fetchOne();

        self::assertIsNumeric($count);

        return (int) $count;
    }
}
