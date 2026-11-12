<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Twig;

use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What the language selector shows (ADR-0001): the languages offered, and each one's flag.
 *
 * Functions rather than Twig globals: the list belongs to `core.locale.supported`, and copying it into
 * `twig.globals` would make a second source of truth. The selector's TEMPLATE stays in the application
 * (or ui-bundle, admin-bundle): it is a layout decision.
 */
final class LocaleExtension extends AbstractExtension
{
    /**
     * Each language's flag, inline SVG.
     *
     * ⚠️ **SVG, not emoji.** 🇫🇷 is tempting — zero bytes of dependency — but Windows has no national
     * flag glyphs: the same page renders "FR" in letters there. Two rectangles weigh less than a
     * request and render the same everywhere.
     *
     * `aria-hidden` on each: the language's name is written next to it, so the flag is decorative —
     * announcing it would read it twice. `preserveAspectRatio="none"`: the template's container
     * imposes the frame, and the flags do not share a ratio (3:2 for France, 2:1 for the United
     * Kingdom, 3:5 for Luxembourg) — without it, those that differ would float in their box.
     *
     * ⚠️ The Netherlands and Luxembourg carry the SAME horizontal tricolour, the only pair a reader can
     * confuse: the blue tells them apart (#21468B against #00A1DE), and the ratio too (2:3 against 3:5).
     *
     * @var array<string, string>
     */
    private const array FLAGS = [
        'fr' => '<svg viewBox="0 0 3 2" preserveAspectRatio="none" class="block w-full h-full" aria-hidden="true" focusable="false">'
            .'<rect width="3" height="2" fill="#ED2939"/>'
            .'<rect width="2" height="2" fill="#fff"/>'
            .'<rect width="1" height="2" fill="#002395"/>'
            .'</svg>',
        // English is shown by the Union Jack: the usage, and the only flag a reader ties to "English"
        // without hesitating.
        'en' => '<svg viewBox="0 0 60 30" preserveAspectRatio="none" class="block w-full h-full" aria-hidden="true" focusable="false">'
            .'<rect width="60" height="30" fill="#012169"/>'
            .'<path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/>'
            .'<path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="4"/>'
            .'<path d="M30,0 V30 M0,15 H60" stroke="#fff" stroke-width="10"/>'
            .'<path d="M30,0 V30 M0,15 H60" stroke="#C8102E" stroke-width="6"/>'
            .'</svg>',
        'nl' => '<svg viewBox="0 0 9 6" preserveAspectRatio="none" class="block w-full h-full" aria-hidden="true" focusable="false">'
            .'<rect width="9" height="6" fill="#21468B"/>'
            .'<rect width="9" height="4" fill="#fff"/>'
            .'<rect width="9" height="2" fill="#AE1C28"/>'
            .'</svg>',
        'de' => '<svg viewBox="0 0 5 3" preserveAspectRatio="none" class="block w-full h-full" aria-hidden="true" focusable="false">'
            .'<rect width="5" height="3" fill="#FFCE00"/>'
            .'<rect width="5" height="2" fill="#D00"/>'
            .'<rect width="5" height="1" fill="#000"/>'
            .'</svg>',
        'lb' => '<svg viewBox="0 0 5 3" preserveAspectRatio="none" class="block w-full h-full" aria-hidden="true" focusable="false">'
            .'<rect width="5" height="3" fill="#00A1DE"/>'
            .'<rect width="5" height="2" fill="#fff"/>'
            .'<rect width="5" height="1" fill="#EA141D"/>'
            .'</svg>',
    ];

    public function __construct(
        private readonly SupportedLocales $locales,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('backoffice_locales', $this->locales->all(...)),
            // `is_safe: html` because the value IS markup, and it comes from a constant of this file —
            // the trust of a template. No user data crosses this table.
            new TwigFunction('locale_flag', $this->flag(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * The flag's markup, or an empty string for a language without one: a language added without its
     * flag renders a slightly duller selector, not a 500 on every page.
     */
    public function flag(string $locale): string
    {
        return self::FLAGS[$locale] ?? '';
    }
}
