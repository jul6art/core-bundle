<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Attribute;

use Jul6Art\CoreBundle\Attribute\Purgeable;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\ConditionalPurgeableLog;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\RepeatablePurgeableLog;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Purgeable::class)]
final class PurgeableTest extends TestCase
{
    public function testItCarriesTheRetentionPolicy(): void
    {
        $purgeable = new Purgeable(field: 'createdAt', interval: '-3 months');

        self::assertSame('createdAt', $purgeable->field);
        self::assertSame('-3 months', $purgeable->interval);
        self::assertSame('', $purgeable->condition);
    }

    public function testTheConditionIsOptional(): void
    {
        self::assertSame('entity.isObsolete()', new Purgeable('createdAt', '-1 day', 'entity.isObsolete()')->condition);
    }

    public function testItTargetsClassesAndIsRepeatable(): void
    {
        $attributes = new \ReflectionClass(Purgeable::class)->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        self::assertSame(
            \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE,
            $attributes[0]->newInstance()->flags
        );
    }

    /** Two policies on one entity is the whole point of IS_REPEATABLE. */
    public function testAnEntityMayDeclareSeveralPolicies(): void
    {
        $attributes = new \ReflectionClass(RepeatablePurgeableLog::class)->getAttributes(Purgeable::class);

        self::assertCount(2, $attributes);
        self::assertSame(
            [['createdAt', '-2 years'], ['deletedAt', '-1 week']],
            array_map(
                static fn (\ReflectionAttribute $a): array => [$a->newInstance()->field, $a->newInstance()->interval],
                $attributes
            )
        );
    }

    public function testItIsReadableFromAnAnnotatedEntity(): void
    {
        $purgeable = new \ReflectionClass(ConditionalPurgeableLog::class)->getAttributes(Purgeable::class)[0]->newInstance();

        self::assertSame('entity.isObsolete()', $purgeable->condition);
    }

    public function testAnUnannotatedEntityCarriesNoPolicy(): void
    {
        self::assertSame([], new \ReflectionClass(Widget::class)->getAttributes(Purgeable::class));
    }
}
