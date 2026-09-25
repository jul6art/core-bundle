<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service;

/**
 * Centralised number formatting for anything a human reads — HTML views, PDFs, JSON exposed to
 * a UI. Going through one service is what keeps a figure looking the same everywhere, instead
 * of each template picking its own `number_format()` arguments.
 *
 * The defaults follow the French / Luxembourg convention: comma as decimal separator,
 * **non-breaking space** as thousands separator. That last choice matters beyond aesthetics —
 * a regular space lets a PDF renderer wrap a number across two lines. Reconfigure both for
 * another locale through `core.number_format`.
 *
 * Display stays at two decimals even where the database keeps four for computation (rates,
 * unit quantities): rounding belongs to the display, not to the storage.
 */
final readonly class NumberFormatter
{
    public const int DEFAULT_DECIMALS = 2;

    /** Non-breaking space (U+00A0): reads as a space, never breaks a line. */
    private const string NBSP = "\u{00A0}";

    public function __construct(
        private string $decimalSeparator = ',',
        private string $thousandsSeparator = self::NBSP,
        private int $decimals = self::DEFAULT_DECIMALS,
    ) {
    }

    /**
     * Formats a number, or returns an empty string when there is nothing to format — so the
     * template decides on the fallback ('—', '-', …) rather than the formatter guessing.
     */
    public function format(int|float|string|null $value, ?int $decimals = null): string
    {
        if (null === $value || '' === $value || !is_numeric($value)) {
            return '';
        }

        return number_format(
            (float) $value,
            max(0, $decimals ?? $this->decimals),
            $this->decimalSeparator,
            $this->thousandsSeparator,
        );
    }

    /**
     * A quantity — a stock level, a threshold, a line quantity — shows only the decimals it has:
     * `2.00` reads `2`, `2.50` reads `2,5`, `0.75` reads `0,75`.
     *
     * A `decimal(_, 2)` column hands back "2.00" for two pieces, and `format()` would print
     * "2,00" — the shape of a price, on something that is counted. Rounding to the configured
     * decimals happens FIRST, then the trailing zeros go: trimming before rounding would turn
     * 1.996 into "1,996".
     */
    public function formatQuantity(int|float|string|null $value): string
    {
        $formatted = $this->format($value);

        if ('' === $formatted || '' === $this->decimalSeparator || !str_contains($formatted, $this->decimalSeparator)) {
            return $formatted;
        }

        [$integer, $fraction] = explode($this->decimalSeparator, $formatted, 2);
        $fraction = rtrim($fraction, '0');

        return '' === $fraction ? $integer : $integer.$this->decimalSeparator.$fraction;
    }

    /**
     * The most frequent pairing: an amount and its currency.
     */
    public function formatMoney(int|float|string|null $value, string $currency, ?int $decimals = null): string
    {
        $formatted = $this->format($value, $decimals);

        return '' === $formatted ? '' : $formatted.' '.$currency;
    }

    /**
     * Percentages — VAT rates, probabilities — default to no decimal, and the sign is glued
     * with a non-breaking space so a PDF renderer never leaves it alone on the next line.
     */
    public function formatPercent(int|float|string|null $value, int $decimals = 0): string
    {
        $formatted = $this->format($value, $decimals);

        return '' === $formatted ? '' : $formatted.self::NBSP.'%';
    }
}
