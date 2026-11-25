<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Test;

use Jul6Art\CoreBundle\Util\Clock;
use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Freezes and moves the time in a test, instead of waiting — or hoping the test does not run at
 * midnight.
 *
 * The frozen clock is the global one: {@see Clock}, the `clock` service, Messenger, Lexik and every
 * service typed `Psr\Clock\ClockInterface` read it. It is put back after each test.
 *
 * ```php
 * self::freezeClock('2026-11-24 10:00');
 * // …
 * self::advanceClock('+15 minutes');
 * ```
 *
 * A test that freezes the time builds the dates it compares with {@see Clock::now()}, not with
 * `new \DateTimeImmutable()`, which reads the system clock.
 */
trait ClockTestTrait
{
    use ClockSensitiveTrait;

    /**
     * Stops the time at `$when`, a date or time string read as `new \DateTimeImmutable()` reads it.
     */
    protected static function freezeClock(string|\DateTimeImmutable $when = 'now'): \DateTimeImmutable
    {
        self::mockTime(\is_string($when) ? Clock::now($when) : $when);

        return Clock::now();
    }

    /**
     * Moves the time by `$modifier` (`'+5 seconds'`, `'+1 day'`, `'-1 hour'`) at once, in place of a
     * `sleep()`. The clock is frozen first if it was not.
     */
    protected static function advanceClock(string $modifier): \DateTimeImmutable
    {
        $clock = SymfonyClock::get();

        if (!$clock instanceof MockClock) {
            $clock = self::mockTime(Clock::now());
        }

        \assert($clock instanceof MockClock);
        $clock->modify($modifier);

        return Clock::now();
    }
}
