<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale;

/**
 * The languages the interface offers, and the one it falls back to.
 *
 * Configured by `core.locale.supported` and `core.locale.default` — by default the kernel's
 * `enabled_locales` and `default_locale`. An application keeps its own list (a `BackofficeLocales`
 * constant, say) and hands it over with `!php/const`: one source of truth, read by the selector, the
 * switch and the resolution alike.
 */
final readonly class SupportedLocales
{
    /** @var list<string> */
    private array $locales;

    /**
     * @param array<array-key, mixed> $locales an empty list means "the default only"
     */
    public function __construct(array $locales, private string $default)
    {
        $list = [];
        foreach ($locales as $locale) {
            if (\is_string($locale) && '' !== $locale && !\in_array($locale, $list, true)) {
                $list[] = $locale;
            }
        }

        $this->locales = [] === $list ? [$default] : $list;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->locales;
    }

    public function default(): string
    {
        return $this->default;
    }

    public function isSupported(string $locale): bool
    {
        return \in_array($locale, $this->locales, true);
    }
}
