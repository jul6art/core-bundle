<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(IdTrait::class)]
final class IdTraitTest extends TestCase
{
    public function testTheIdIsNullUntilPersisted(): void
    {
        self::assertNull(new Widget()->getId());
    }

    public function testTheIdIsATypedNullableInteger(): void
    {
        self::assertSame('?int', (string) $this->idProperty()->getType());
    }

    /**
     * The mapping moved from annotations to attributes; without them Doctrine sees no
     * identifier at all.
     */
    public function testTheMappingUsesAttributes(): void
    {
        $names = array_map(
            static fn (\ReflectionAttribute $attribute): string => $attribute->getName(),
            $this->idProperty()->getAttributes()
        );

        self::assertContains(ORM\Id::class, $names);
        self::assertContains(ORM\Column::class, $names);
        self::assertContains(ORM\GeneratedValue::class, $names);
    }

    public function testTheColumnIsAnInteger(): void
    {
        $attributes = $this->idProperty()->getAttributes(ORM\Column::class);
        self::assertCount(1, $attributes);

        self::assertSame(Types::INTEGER, $attributes[0]->newInstance()->type);
    }

    public function testTheIdentifierUsesTheIdentityStrategy(): void
    {
        $attributes = $this->idProperty()->getAttributes(ORM\GeneratedValue::class);
        self::assertCount(1, $attributes);

        self::assertSame('IDENTITY', $attributes[0]->newInstance()->strategy);
    }

    private function idProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(Widget::class, 'id');
    }
}
