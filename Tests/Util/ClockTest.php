<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Util;

use Jul6Art\CoreBundle\Test\ClockTestTrait;
use Jul6Art\CoreBundle\Util\Clock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock as SymfonyClock;

/**
 * The helper must give what the native constructor gives — under the system clock, where nothing
 * may change, and under a frozen one, where it must read the frozen moment as the constructor
 * would have read it.
 */
#[CoversClass(Clock::class)]
final class ClockTest extends TestCase
{
    use ClockTestTrait;

    private const string FORMAT = 'Y-m-d H:i:s.u e';

    private const string FROZEN = '2026-11-24 10:15:30.123456';

    private string $defaultTimezone;

    #[\Override]
    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Brussels');
    }

    #[\Override]
    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    /** @return iterable<string, array{string}> */
    public static function stringsReadTheSameWithinAMinute(): iterable
    {
        foreach (['today', 'midnight', 'tomorrow', 'yesterday', 'today 10:00', 'tomorrow noon', 'first day of next month midnight', 'monday next week', '2026-01-01', '2026-01-01 10:00:00', '@1700000000', '2026-07-14 08:00:00 UTC', '2026-03-29 02:30:00'] as $string) {
            yield $string => [$string];
        }
    }

    #[DataProvider('stringsReadTheSameWithinAMinute')]
    public function testNowGivesWhatTheNativeConstructorGives(string $string): void
    {
        $before = new \DateTimeImmutable($string);
        $now = Clock::now($string);
        $after = new \DateTimeImmutable($string);

        self::assertContains($now->format(self::FORMAT), [$before->format(self::FORMAT), $after->format(self::FORMAT)]);
        self::assertSame(\DateTimeImmutable::class, $now::class, 'A plain date, never a Symfony class.');
    }

    /** @return iterable<string, array{string}> */
    public static function stringsThatKeepTheCurrentTime(): iterable
    {
        foreach (['now', '+3 days', '-14 days', '+1 hour', '-90 minutes', 'first day of next month'] as $string) {
            yield $string => [$string];
        }
    }

    #[DataProvider('stringsThatKeepTheCurrentTime')]
    public function testNowKeepsTheCurrentTimeLikeTheNativeConstructor(string $string): void
    {
        $before = new \DateTimeImmutable($string);
        $now = Clock::now($string);
        $after = new \DateTimeImmutable($string);

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
        self::assertSame($before->getTimezone()->getName(), $now->getTimezone()->getName());
    }

    /** @return iterable<string, array{string, string}> */
    public static function frozenReadings(): iterable
    {
        yield 'now' => ['now', '2026-11-24 10:15:30.123456 Europe/Brussels'];
        yield 'today' => ['today', '2026-11-24 00:00:00.000000 Europe/Brussels'];
        yield 'midnight' => ['midnight', '2026-11-24 00:00:00.000000 Europe/Brussels'];
        yield 'tomorrow' => ['tomorrow', '2026-11-25 00:00:00.000000 Europe/Brussels'];
        yield '+3 days' => ['+3 days', '2026-11-27 10:15:30.123456 Europe/Brussels'];
        yield '-14 days' => ['-14 days', '2026-11-10 10:15:30.123456 Europe/Brussels'];
        yield 'a time alone' => ['10:00', '2026-11-24 10:00:00.000000 Europe/Brussels'];
        yield 'first day of next month' => ['first day of next month', '2026-12-01 10:15:30.123456 Europe/Brussels'];
        yield 'a full date ignores the clock' => ['2026-01-01', '2026-01-01 00:00:00.000000 Europe/Brussels'];
        yield 'a timestamp ignores the clock' => ['@1700000000', '2023-11-14 22:13:20.000000 +00:00'];
    }

    #[DataProvider('frozenReadings')]
    public function testNowReadsAFrozenClock(string $string, string $expected): void
    {
        self::freezeClock(self::FROZEN);

        self::assertSame($expected, Clock::now($string)->format(self::FORMAT));
    }

    public function testNowTakesATimezoneLikeTheNativeConstructor(): void
    {
        self::freezeClock(self::FROZEN);

        self::assertSame('2026-11-24 09:15:30.123456 UTC', Clock::now('now', new \DateTimeZone('UTC'))->format(self::FORMAT));
        self::assertSame('2026-11-24 00:00:00.000000 UTC', Clock::now('today', new \DateTimeZone('UTC'))->format(self::FORMAT));
    }

    /**
     * The native constructor reads the default timezone in force when it is called; the global
     * clock keeps the one of its creation.
     */
    public function testNowReadsTheDefaultTimezoneInForce(): void
    {
        SymfonyClock::get()->now();
        date_default_timezone_set('America/New_York');

        self::assertSame('America/New_York', Clock::now()->getTimezone()->getName());
        self::assertSame(new \DateTimeImmutable('today')->format(self::FORMAT), Clock::now('today')->format(self::FORMAT));
    }

    public function testTimestampReadsTheClock(): void
    {
        self::freezeClock(self::FROZEN);

        self::assertSame(new \DateTimeImmutable(self::FROZEN)->getTimestamp(), Clock::timestamp());
    }

    public function testTimestampGivesWhatTimeGives(): void
    {
        $before = time();
        $timestamp = Clock::timestamp();

        self::assertGreaterThanOrEqual($before, $timestamp);
        self::assertLessThanOrEqual(time(), $timestamp);
    }

    public function testSleepMovesAFrozenClockWithoutWaiting(): void
    {
        self::freezeClock(self::FROZEN);
        $started = hrtime(true);

        Clock::sleep(3600);

        self::assertSame('2026-11-24 11:15:30.123456 Europe/Brussels', Clock::now()->format(self::FORMAT));
        self::assertLessThan(1_000_000_000, hrtime(true) - $started);
    }

    public function testAStringThatIsNotADateIsRefusedLikeByTheNativeConstructor(): void
    {
        $this->expectException(\DateMalformedStringException::class);

        Clock::now('not a date');
    }
}
