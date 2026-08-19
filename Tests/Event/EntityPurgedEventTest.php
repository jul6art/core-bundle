<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Event;

use Jul6Art\CoreBundle\Event\AbstractEvent;
use Jul6Art\CoreBundle\Event\EntityPurgedEvent;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\PurgeableLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The event is how a purge reaches an audit trail without the command knowing anything about
 * one: it carries scalars only, because by the time it is dispatched the entity has been
 * removed and flushed, so it is detached.
 */
#[CoversClass(EntityPurgedEvent::class)]
final class EntityPurgedEventTest extends TestCase
{
    public function testItCarriesEverythingAnAuditTrailNeeds(): void
    {
        $event = new EntityPurgedEvent(
            entityClass: PurgeableLog::class,
            entityShortName: 'PurgeableLog',
            entityId: 42,
            organizationId: 7,
            interval: '-3 months',
            condition: 'entity.isObsolete()',
        );

        self::assertSame(PurgeableLog::class, $event->getEntityClass());
        self::assertSame('PurgeableLog', $event->getEntityShortName());
        self::assertSame(42, $event->getEntityId());
        self::assertSame(7, $event->getOrganizationId());
        self::assertSame('-3 months', $event->getInterval());
        self::assertSame('entity.isObsolete()', $event->getCondition());
    }

    public function testTheTenantAndTheConditionAreOptional(): void
    {
        $event = new EntityPurgedEvent(PurgeableLog::class, 'PurgeableLog', 'uuid-1', null, '-1 day');

        self::assertNull($event->getOrganizationId());
        self::assertSame('', $event->getCondition());
        self::assertSame('uuid-1', $event->getEntityId(), 'A non-integer identifier must survive.');
    }

    /** It plugs into the bundle's event plumbing, so a subscriber can carry extra context. */
    public function testItIsABundleEvent(): void
    {
        $event = new EntityPurgedEvent(PurgeableLog::class, 'PurgeableLog', 1, null, '-1 day');

        self::assertContains(AbstractEvent::class, class_parents($event));
        self::assertCount(0, $event->getData());
    }

    public function testItsNameIsPublished(): void
    {
        self::assertSame('core.entity.purged', new \ReflectionClassConstant(EntityPurgedEvent::class, 'NAME')->getValue());
    }
}
