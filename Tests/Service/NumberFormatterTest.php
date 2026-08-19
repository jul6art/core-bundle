<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Service;

use Jul6Art\CoreBundle\Service\NumberFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumberFormatter::class)]
final class NumberFormatterTest extends TestCase
{
    /** Non-breaking space: it looks like a space but never lets a number wrap. */
    private const string NBSP = "\u{00A0}";

    /** @return iterable<string, array{int|float|string|null, string}> */
    public static function formatProvider(): iterable
    {
        yield 'integer' => [42, '42,00'];
        yield 'float' => [1234.5, '1'.self::NBSP.'234,50'];
        yield 'numeric string' => ['1234.56', '1'.self::NBSP.'234,56'];
        yield 'millions get every group' => [1234567.89, '1'.self::NBSP.'234'.self::NBSP.'567,89'];
        yield 'rounds up' => [1.005, '1,01'];
        yield 'rounds down' => [1.004, '1,00'];
        yield 'negative' => [-1234.5, '-1'.self::NBSP.'234,50'];
        yield 'zero' => [0, '0,00'];
    }

    /**
     * Null returns an empty string so the template decides on the fallback ('—', '-', …)
     * instead of the formatter guessing.
     *
     * @return iterable<string, array{int|float|string|null}>
     */
    public static function emptyProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'letters' => ['abc'];
        yield 'currency-looking string' => ['12,34 €'];
    }

    #[DataProvider('formatProvider')]
    public function testFormat(int|float|string|null $value, string $expected): void
    {
        self::assertSame($expected, new NumberFormatter()->format($value));
    }

    #[DataProvider('emptyProvider')]
    public function testAValueThatIsNotANumberYieldsAnEmptyString(int|float|string|null $value): void
    {
        self::assertSame('', new NumberFormatter()->format($value));
    }

    public function testTheNumberOfDecimalsIsPerCall(): void
    {
        $formatter = new NumberFormatter();

        self::assertSame('1'.self::NBSP.'235', $formatter->format(1234.56, 0));
        self::assertSame('1'.self::NBSP.'234,560', $formatter->format(1234.56, 3));
    }

    /** A negative count would be nonsense; it is clamped rather than fatal. */
    public function testANegativeDecimalCountIsClampedToZero(): void
    {
        self::assertSame('1'.self::NBSP.'235', new NumberFormatter()->format(1234.56, -2));
    }

    public function testMoneyAppendsTheCurrency(): void
    {
        self::assertSame('1'.self::NBSP.'234,56 EUR', new NumberFormatter()->formatMoney(1234.56, 'EUR'));
    }

    public function testMoneyOfNothingIsNothing(): void
    {
        self::assertSame('', new NumberFormatter()->formatMoney(null, 'EUR'));
    }

    /**
     * The percent sign is glued with a non-breaking space so a PDF renderer never splits
     * "20 %" across two lines.
     */
    public function testPercentDefaultsToNoDecimalAndKeepsTheSignAttached(): void
    {
        self::assertSame('20'.self::NBSP.'%', new NumberFormatter()->formatPercent(20));
        self::assertSame('20,50'.self::NBSP.'%', new NumberFormatter()->formatPercent(20.5, 2));
    }

    /** Rounding happens inside the percent too, not only in format(). */
    public function testPercentRoundsBeforeAppendingTheSign(): void
    {
        $formatter = new NumberFormatter();

        self::assertSame('21'.self::NBSP.'%', $formatter->formatPercent(20.5));
        self::assertSame('1'.self::NBSP.'234'.self::NBSP.'%', $formatter->formatPercent(1234));
    }

    public function testPercentOfNothingIsNothing(): void
    {
        self::assertSame('', new NumberFormatter()->formatPercent(null));
    }

    // ── séparateurs configurables ─────────────────────────────────────────

    /** The defaults follow the French convention; another locale reconfigures them. */
    public function testTheSeparatorsAreConfigurable(): void
    {
        $english = new NumberFormatter(decimalSeparator: '.', thousandsSeparator: ',');

        self::assertSame('1,234.56', $english->format(1234.56));
        self::assertSame('1,234.56 USD', $english->formatMoney(1234.56, 'USD'));
    }

    public function testTheDefaultDecimalCountIsConfigurable(): void
    {
        self::assertSame('1'.self::NBSP.'234,5678', new NumberFormatter(decimals: 4)->format(1234.5678));
    }

    public function testSeparatorsCanBeDroppedEntirely(): void
    {
        self::assertSame('1234,56', new NumberFormatter(thousandsSeparator: '')->format(1234.56));
    }
}
