<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Util;

use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\Clock\DatePoint;

/**
 * The one place that reads the time.
 *
 * Every reading goes through the global clock of Symfony — the one the `clock` service, Messenger,
 * Lexik and every service typed `Psr\Clock\ClockInterface` read too — so a test that freezes it
 * ({@see \Jul6Art\CoreBundle\Test\ClockTestTrait}) freezes them all at once. A service keeps
 * injecting the PSR clock; an entity, a trait or a static helper, which cannot have it injected,
 * calls this class. Should the implementation ever change, this file is the only one to touch.
 *
 * ```php
 * Clock::now();             // new \DateTimeImmutable()
 * Clock::now('-14 days');   // new \DateTimeImmutable('-14 days')
 * Clock::timestamp();       // time()
 * Clock::sleep(2);          // sleep(2), skipped by a frozen clock
 * ```
 *
 * `now()` takes the arguments of the native constructor and returns what it would, relative to the
 * clock: `'today'` is midnight, `'+3 days'` keeps the time, a full date ignores the clock. A plain
 * `\DateTimeImmutable` comes out, never a Symfony class, so none ends up in a serialized value, a
 * cache entry or a message.
 */
final class Clock
{
    /**
     * The current time, as `new \DateTimeImmutable($modifier, $timezone)` would give it.
     *
     * @throws \DateMalformedStringException when `$modifier` is not a date or time string
     */
    public static function now(string $modifier = 'now', ?\DateTimeZone $timezone = null): \DateTimeImmutable
    {
        // The timezone in force when called, as the native constructor reads it — the global
        // clock keeps the one of its creation.
        $timezone ??= new \DateTimeZone(date_default_timezone_get());

        return \DateTimeImmutable::createFromInterface(new DatePoint($modifier, $timezone));
    }

    /**
     * The current Unix timestamp, as `time()` gives it.
     */
    public static function timestamp(): int
    {
        return self::now()->getTimestamp();
    }

    /**
     * Waits `$seconds`, as `sleep()` and `usleep()` do; a frozen clock moves forward instead.
     */
    public static function sleep(float|int $seconds): void
    {
        SymfonyClock::get()->sleep($seconds);
    }
}
