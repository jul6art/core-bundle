<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Service;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Jul6Art\CoreBundle\Attribute\Purgeable;
use Jul6Art\CoreBundle\Service\BulkPurgeGuard;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\BulkPurgeableLog;
use Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity\BulkCallbackLog;
use Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity\BulkCascadingParent;
use Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity\BulkConditionalLog;
use Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity\BulkOrphanParent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ⚠️ Bulk never skips a cascade: an entity whose removal the ORM would propagate (cascade, orphan removal) or
 * observe (a remove callback) is REFUSED — explicitly, naming the reason — instead of being deleted around it.
 */
#[CoversClass(BulkPurgeGuard::class)]
final class BulkPurgeGuardTest extends TestCase
{
    /** @return iterable<string, array{class-string, string}> */
    public static function refused(): iterable
    {
        yield 'ORM cascade' => [BulkCascadingParent::class, 'cascades the removal of "children"'];
        yield 'orphan removal' => [BulkOrphanParent::class, 'removes orphans on "attachment"'];
        yield 'remove callback' => [BulkCallbackLog::class, 'has a postRemove lifecycle callback'];
        yield 'condition' => [BulkConditionalLog::class, 'has a condition'];
    }

    /** @param class-string $class */
    #[DataProvider('refused')]
    public function testBulkIsRefusedWhenTheOrmWouldDoSomethingOnRemove(string $class, string $reason): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($reason, '/').'/');

        BulkPurgeGuard::assertSafe($this->entityManager()->getClassMetadata($class), self::purgeable($class));
    }

    public function testAPlainLogIsAcceptedForBulk(): void
    {
        BulkPurgeGuard::assertSafe($this->entityManager()->getClassMetadata(BulkPurgeableLog::class), self::purgeable(BulkPurgeableLog::class));

        $this->addToAssertionCount(1);
    }

    /** @param class-string $class */
    private static function purgeable(string $class): Purgeable
    {
        return new \ReflectionClass($class)->getAttributes(Purgeable::class)[0]->newInstance();
    }

    private function entityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/../Fixtures/Entity', __DIR__.'/../Fixtures/MisconfiguredEntity'], true);
        $config->enableNativeLazyObjects(true);

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
    }
}
