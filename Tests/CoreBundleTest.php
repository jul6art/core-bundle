<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests;

use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\CoreBundle\DependencyInjection\CoreExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoreBundle::class)]
final class CoreBundleTest extends TestCase
{
    public function testItResolvesTheCoreExtensionByConvention(): void
    {
        $extension = new CoreBundle()->getContainerExtension();

        self::assertInstanceOf(CoreExtension::class, $extension);
        self::assertSame('core', $extension->getAlias());
    }

    public function testItsPathPointsAtTheBundleRoot(): void
    {
        $bundle = new CoreBundle();

        self::assertSame('CoreBundle', $bundle->getName());
        self::assertFileExists($bundle->getPath().'/Resources/config/services.yaml');
    }
}
