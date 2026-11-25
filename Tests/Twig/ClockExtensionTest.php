<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Twig;

use Jul6Art\CoreBundle\Test\ClockTestTrait;
use Jul6Art\CoreBundle\Twig\ClockExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Rendered through a real Twig environment: the function must exist under the name templates use.
 */
#[CoversClass(ClockExtension::class)]
final class ClockExtensionTest extends TestCase
{
    use ClockTestTrait;

    public function testClockNowReadsTheFrozenClock(): void
    {
        self::freezeClock('2026-11-24 10:00');

        self::assertSame('2026-11-24 10:00 / 2026-11-25', $this->render("{{ clock_now()|date('Y-m-d H:i') }} / {{ clock_now('+1 day')|date('Y-m-d') }}"));
    }

    public function testClockNowGivesTheYearOfTheClock(): void
    {
        self::freezeClock('2031-01-01 00:30');

        self::assertSame('2031', $this->render("{{ clock_now()|date('Y') }}"));
    }

    private function render(string $template): string
    {
        $twig = new Environment(new ArrayLoader(['template' => $template]));
        $twig->addExtension(new ClockExtension());

        return $twig->render('template');
    }
}
