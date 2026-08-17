<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Factory\Interfaces;

use Jul6Art\CoreBundle\Factory\Interfaces\FactoryInterface;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Jul6Art\CoreBundle\Tests\Fixtures\Factory\WidgetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FactoryInterface::class)]
final class FactoryInterfaceTest extends TestCase
{
    public function testItBuildsAnObjectWithoutArguments(): void
    {
        $widget = WidgetFactory::create();

        self::assertSame(Widget::class, $widget::class);
        self::assertSame('widget', $widget->getName());
    }

    public function testTheImplementationSatisfiesTheContract(): void
    {
        self::assertTrue(
            new \ReflectionClass(WidgetFactory::class)->implementsInterface(FactoryInterface::class)
        );
    }

    public function testItForwardsVariadicArguments(): void
    {
        self::assertSame('gizmo', WidgetFactory::create('gizmo')->getName());
    }

    public function testTheContractDeclaresAVariadicMixedSignature(): void
    {
        $parameters = new \ReflectionMethod(FactoryInterface::class, 'create')->getParameters();

        self::assertCount(1, $parameters);
        self::assertTrue($parameters[0]->isVariadic());
        self::assertSame('mixed', (string) $parameters[0]->getType());
    }
}
