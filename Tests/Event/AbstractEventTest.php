<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Event;

use Doctrine\Common\Collections\ArrayCollection;
use Jul6Art\CoreBundle\Event\AbstractEvent;
use Jul6Art\CoreBundle\Event\Interfaces\EventInterface;
use Jul6Art\CoreBundle\Tests\Fixtures\Event\WidgetEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

#[CoversClass(AbstractEvent::class)]
final class AbstractEventTest extends TestCase
{
    public function testItIsADispatchableEvent(): void
    {
        $reflection = new \ReflectionClass(AbstractEvent::class);

        self::assertTrue($reflection->isSubclassOf(Event::class));
        self::assertTrue($reflection->implementsInterface(EventInterface::class));
    }

    /**
     * These names are part of the public API: consumers dispatch and listen on them.
     */
    public function testTheEventNamesAreStable(): void
    {
        self::assertSame([
            'CREATED' => 'event.created',
            'DELETED' => 'event.deleted',
            'EDITED' => 'event.edited',
            'VIEWED' => 'event.viewed',
        ], new \ReflectionClass(AbstractEvent::class)->getConstants());
    }

    /**
     * They are declared as typed constants, which PHP only allows since 8.3.
     */
    public function testTheEventNamesAreTypedConstants(): void
    {
        foreach (['CREATED', 'DELETED', 'EDITED', 'VIEWED'] as $name) {
            $type = new \ReflectionClassConstant(AbstractEvent::class, $name)->getType();

            self::assertNotNull($type, \sprintf('%s should be a typed constant.', $name));
            self::assertSame('string', (string) $type);
        }
    }

    public function testTheDataCollectionStartsEmpty(): void
    {
        self::assertTrue(new WidgetEvent()->getData()->isEmpty());
    }

    public function testSetDataReplacesTheCollection(): void
    {
        $event = new WidgetEvent();
        $collection = $this->collection(['a', 'b']);

        $event->setData($collection);

        self::assertSame($collection, $event->getData());
    }

    public function testAddDataIgnoresDuplicates(): void
    {
        $event = new WidgetEvent();

        $event->addData('a')->addData('b')->addData('a');

        self::assertSame(['a', 'b'], array_values($event->getData()->toArray()));
    }

    public function testAddDataAcceptsAnyType(): void
    {
        $object = new \stdClass();
        $event = new WidgetEvent()->addData(1)->addData('1')->addData($object)->addData(null);

        self::assertCount(4, $event->getData());
        self::assertTrue($event->getData()->contains($object));
    }

    public function testRemoveDataDropsTheElement(): void
    {
        $event = new WidgetEvent()->addData('a')->addData('b');

        $event->removeData('a');

        self::assertSame(['b'], array_values($event->getData()->toArray()));
    }

    public function testRemoveDataIsSafeForUnknownElements(): void
    {
        $event = new WidgetEvent()->addData('a');

        $event->removeData('missing');

        self::assertSame(['a'], array_values($event->getData()->toArray()));
    }

    /**
     * The setters are declared as ": static", so chaining must keep the concrete
     * instance rather than degrade to the abstract type.
     */
    public function testTheFluentSettersReturnTheSameInstance(): void
    {
        $event = new WidgetEvent();

        self::assertSame($event, $event->setData($this->collection()));
        self::assertSame($event, $event->addData('a'));
        self::assertSame($event, $event->removeData('a'));
    }

    /**
     * @param list<mixed> $items
     *
     * @return ArrayCollection<int, mixed>
     */
    private function collection(array $items = []): ArrayCollection
    {
        return new ArrayCollection($items);
    }
}
