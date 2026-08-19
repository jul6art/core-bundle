<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Jul6Art\CoreBundle\Doctrine\SoftDeleteFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SoftDeleteFilter::class)]
final class SoftDeleteFilterTest extends TestCase
{
    public function testItExcludesSoftDeletedRowsOfAnEntityThatHasTheField(): void
    {
        self::assertSame('e.deleted_at IS NULL', $this->constraintFor(hasField: true, alias: 'e'));
    }

    public function testItUsesTheAliasItIsGiven(): void
    {
        self::assertSame('t0_.deleted_at IS NULL', $this->constraintFor(hasField: true, alias: 't0_'));
    }

    /**
     * The column name must come from the mapping. Doctrine's default naming strategy keeps
     * the property name (`deletedAt`); only an underscore strategy yields `deleted_at`.
     * Hard-coding either one breaks every application using the other.
     */
    public function testItHonoursTheNamingStrategyInsteadOfAssumingSnakeCase(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects(self::atLeastOnce())->method('hasField')->with('deletedAt')->willReturn(true);
        $metadata->expects(self::atLeastOnce())->method('getColumnName')->with('deletedAt')->willReturn('deletedAt');

        $filter = new SoftDeleteFilter(self::createStub(EntityManagerInterface::class));

        self::assertSame('g0_.deletedAt IS NULL', $filter->addFilterConstraint($metadata, 'g0_'));
    }

    /**
     * An entity without `deletedAt` must produce no constraint at all — returning
     * anything else would inject `deleted_at IS NULL` on a table that has no such
     * column and break every query.
     */
    public function testItStaysOutOfTheWayForAnEntityWithoutTheField(): void
    {
        self::assertSame('', $this->constraintFor(hasField: false, alias: 'e'));
    }

    private function constraintFor(bool $hasField, string $alias): string
    {
        // A stub, not a mock: this helper only shapes the metadata. That the filter asks for
        // the right field and honours the mapping is asserted by
        // testItHonoursTheNamingStrategyInsteadOfAssumingSnakeCase().
        $metadata = self::createStub(ClassMetadata::class);
        $metadata->method('hasField')->willReturn($hasField);
        $metadata->method('getColumnName')->willReturn('deleted_at');

        $filter = new SoftDeleteFilter(self::createStub(EntityManagerInterface::class));

        return $filter->addFilterConstraint($metadata, $alias);
    }
}
