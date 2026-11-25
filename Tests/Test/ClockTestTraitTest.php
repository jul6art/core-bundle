<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Test;

use Jul6Art\CoreBundle\Test\ClockTestTrait;
use Jul6Art\CoreBundle\Util\Clock;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

#[CoversTrait(ClockTestTrait::class)]
final class ClockTestTraitTest extends TestCase
{
    use ClockTestTrait;

    private const string FORMAT = 'Y-m-d H:i:s';

    public function testFreezeClockStopsTheTime(): void
    {
        $frozen = self::freezeClock('2026-11-24 10:00');

        self::assertSame('2026-11-24 10:00:00', $frozen->format(self::FORMAT));
        self::assertEquals($frozen, Clock::now());
    }

    /**
     * The `clock` service of the framework is a Symfony Clock without a clock of its own: it reads
     * the frozen one too, and so does every service typed with the PSR interface.
     */
    public function testTheClockServiceReadsTheFrozenClock(): void
    {
        self::freezeClock('2026-11-24 10:00');

        self::assertSame('2026-11-24 10:00:00', new SymfonyClock()->now()->format(self::FORMAT));
    }

    public function testFreezeClockTakesADate(): void
    {
        $date = new \DateTimeImmutable('2026-11-24 10:00');

        self::freezeClock($date);

        self::assertEquals($date, Clock::now());
    }

    public function testAdvanceClockMovesTheFrozenTime(): void
    {
        self::freezeClock('2026-11-24 10:00');

        $moved = self::advanceClock('+5 seconds');

        self::assertSame('2026-11-24 10:00:05', $moved->format(self::FORMAT));
        self::assertSame('2026-11-24 10:00:05', Clock::now()->format(self::FORMAT));
    }

    public function testAdvanceClockFreezesTheClockFirst(): void
    {
        $before = Clock::now();

        $moved = self::advanceClock('+1 day');

        self::assertInstanceOf(MockClock::class, SymfonyClock::get());
        self::assertGreaterThanOrEqual($before->modify('+1 day'), $moved);
        self::assertEquals($moved, Clock::now());
    }

    #[Depends('testAdvanceClockFreezesTheClockFirst')]
    public function testTheClockIsPutBackAfterEachTest(): void
    {
        self::assertInstanceOf(NativeClock::class, SymfonyClock::get());
    }
}
