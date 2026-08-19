<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Event;

use Jul6Art\CoreBundle\Event\PersistenceAbortedException;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Completes the abort protocol of AbstractEntityListener: the listener throws this
 * exception when a BEFORE_* event was aborted, so the flush never happens.
 */
#[CoversClass(PersistenceAbortedException::class)]
final class PersistenceAbortedExceptionTest extends TestCase
{
    public function testTheMessageNamesTheEntityClass(): void
    {
        $exception = new PersistenceAbortedException(new Widget());

        self::assertSame('Persistence of '.Widget::class.' aborted', $exception->getMessage());
    }

    public function testTheMessageCarriesTheReasonWhenGiven(): void
    {
        $exception = new PersistenceAbortedException(new Widget(), 'quota exceeded');

        self::assertSame('Persistence of '.Widget::class.' aborted: quota exceeded', $exception->getMessage());
    }

    public function testTheAbortedEntityIsRetrievable(): void
    {
        $widget = new Widget('doomed');

        self::assertSame($widget, new PersistenceAbortedException($widget)->getAbortedEntity());
    }
}
