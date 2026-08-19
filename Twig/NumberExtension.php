<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Twig;

use Jul6Art\CoreBundle\Service\NumberFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Exposes {@see NumberFormatter} to templates, so a figure shown to a human never goes through
 * a hand-written `|number_format(...)` again.
 *
 * ```twig
 * {{ invoice.total|format_number }}        {# 1 234,56 #}
 * {{ invoice.total|format_number(0) }}     {# 1 235 #}
 * {{ invoice.total|format_money('EUR') }}  {# 1 234,56 EUR #}
 * {{ line.vatRate|format_percent }}        {# 20 % #}
 * ```
 *
 * Each filter is registered twice: under a neutral name — what a new project should use — and
 * under the historical `fr_*` name, kept because hundreds of templates already call it.
 */
final class NumberExtension extends AbstractExtension
{
    public function __construct(
        private readonly NumberFormatter $formatter,
    ) {
    }

    /**
     * @return list<TwigFilter>
     */
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_number', $this->number(...)),
            new TwigFilter('format_money', $this->money(...)),
            new TwigFilter('format_percent', $this->percent(...)),
            // Historical names, same behaviour.
            new TwigFilter('fr_number', $this->number(...)),
            new TwigFilter('fr_money', $this->money(...)),
            new TwigFilter('fr_percent', $this->percent(...)),
        ];
    }

    public function number(int|float|string|null $value, ?int $decimals = null): string
    {
        return $this->formatter->format($value, $decimals);
    }

    public function money(int|float|string|null $value, string $currency, ?int $decimals = null): string
    {
        return $this->formatter->formatMoney($value, $currency, $decimals);
    }

    public function percent(int|float|string|null $value, int $decimals = 0): string
    {
        return $this->formatter->formatPercent($value, $decimals);
    }
}
