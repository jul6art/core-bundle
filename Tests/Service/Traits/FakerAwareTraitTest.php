<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Service\Traits;

use Faker\Generator;
use Jul6Art\CoreBundle\Service\Traits\FakerAwareTrait;
use Jul6Art\CoreBundle\Tests\Fixtures\Service\AwareService;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(FakerAwareTrait::class)]
final class FakerAwareTraitTest extends TestCase
{
    /**
     * The property is a non-nullable typed property, so reading it before the setter
     * ran is an error rather than a silent null.
     */
    public function testTheGeneratorIsOnlyAvailableOnceTheSetterRan(): void
    {
        $service = new AwareService();

        $this->expectException(\Error::class);
        $this->expectExceptionMessageIsOrContains('must not be accessed before initialization');

        $generator = $service->faker();

        self::fail(\sprintf('Reading the generator before setFaker() should fail, got "%s".', $generator::class));
    }

    public function testTheSetterBuildsAGenerator(): void
    {
        $service = new AwareService();

        $service->setFaker();

        self::assertSame(Generator::class, $service->faker()::class);
    }

    /**
     * The setter takes no argument, so the container can call it as a plain method
     * call; it must be usable straight away.
     */
    public function testTheGeneratorIsUsable(): void
    {
        $service = new AwareService();
        $service->setFaker();

        self::assertNotSame('', $service->faker()->name());
    }

    public function testTheSetterTakesNoArgument(): void
    {
        self::assertSame(0, new \ReflectionMethod(AwareService::class, 'setFaker')->getNumberOfParameters());
    }
}
