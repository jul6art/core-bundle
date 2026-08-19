<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Form\Extension;

use Jul6Art\CoreBundle\Form\Extension\NumberTypeGroupingExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

/**
 * Symfony leaves `grouping` off, so a quantity renders as "1234.56" where the convention is
 * "1 234,56". The extension flips that default for every NumberType at once — which is why it
 * is opt-in on the bundle side: it changes how every numeric field of an application looks.
 */
#[CoversClass(NumberTypeGroupingExtension::class)]
final class NumberTypeGroupingExtensionTest extends TestCase
{
    public function testGroupingIsOffWithoutTheExtension(): void
    {
        self::assertFalse($this->factory(withExtension: false)->create(NumberType::class)->getConfig()->getOption('grouping'));
    }

    public function testTheExtensionTurnsGroupingOn(): void
    {
        self::assertTrue($this->factory()->create(NumberType::class)->getConfig()->getOption('grouping'));
    }

    /** A field that must not be grouped — a numeric identifier — can still say so. */
    public function testAFieldCanStillOptOut(): void
    {
        $form = $this->factory()->create(NumberType::class, null, ['grouping' => false]);

        self::assertFalse($form->getConfig()->getOption('grouping'));
    }

    public function testItAppliesToNumberTypeOnly(): void
    {
        self::assertSame([NumberType::class], [...NumberTypeGroupingExtension::getExtendedTypes()]);
    }

    private function factory(bool $withExtension = true): FormFactoryInterface
    {
        $builder = Forms::createFormFactoryBuilder();

        if ($withExtension) {
            $builder->addTypeExtension(new NumberTypeGroupingExtension());
        }

        return $builder->getFormFactory();
    }
}
