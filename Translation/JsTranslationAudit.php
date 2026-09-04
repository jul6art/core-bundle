<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation;

use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Confronts the keys the JavaScript reads with the keys the catalogue holds, in the single
 * domain the browser is allowed to see.
 *
 * ## Why both directions
 *
 * The direction everyone expects — a key read but not translated — puts a raw key on a user's
 * screen. The other one matters just as much here: when a project exposes exactly one domain to
 * bound what leaves the server, a key nobody reads is weight shipped to every visitor, and
 * nothing else in the toolchain will ever mention it.
 *
 * ## Why `defines()` and not `has()`
 *
 * ⚠️ `MessageCatalogue::has()` walks the fallback catalogue, so a key translated in `en` answers
 * yes for `fr`. That is the right behaviour at runtime and the wrong one here: it is precisely
 * what lets a half-translated locale look complete. The audit asks `defines()`, which only ever
 * looks at the locale it was given.
 */
final readonly class JsTranslationAudit
{
    public function __construct(
        private TranslatorBagInterface $translator,
        private string $domain = 'javascript',
    ) {
    }

    /**
     * @param list<string> $locales          every locale the application serves — not just the default
     * @param list<string> $declaredKeys     keys the project vouches for without the scanner seeing
     *                                       them, typically an enum's cases. They are expected to
     *                                       exist, and they are never reported as dead
     * @param list<string> $declaredPrefixes a weaker promise: everything under the prefix is
     *                                       alive, and nothing under it is required. ⚠️ The
     *                                       confirmation modals of `datatable-bundle` read
     *                                       `datatable.modal.<action>.<field>` and fall back to a
     *                                       generic text when the key is absent — the absence is
     *                                       designed. As keys, every type a project leaves
     *                                       generic would be reported missing; declared nowhere,
     *                                       every type it customises would be reported dead
     */
    public function audit(JsTranslationScan $scan, array $locales, array $declaredKeys = [], array $declaredPrefixes = []): JsTranslationReport
    {
        $expected = [...$scan->keys(), ...$declaredKeys];
        sort($expected);
        $expected = array_values(array_unique($expected));

        return new JsTranslationReport(
            $this->missing($expected, $locales),
            $this->unused($scan, $locales, $declaredKeys, $declaredPrefixes),
            $scan->dynamicCalls(),
            $scan,
        );
    }

    /**
     * @param list<string> $expected
     * @param list<string> $locales
     *
     * @return array<string, list<string>>
     */
    private function missing(array $expected, array $locales): array
    {
        $missing = [];

        foreach ($expected as $key) {
            foreach ($locales as $locale) {
                if (!$this->translator->getCatalogue($locale)->defines($key, $this->domain)) {
                    $missing[$key][] = $locale;
                }
            }
        }

        foreach ($missing as $key => $locales) {
            sort($locales);
            $missing[$key] = $locales;
        }

        return $missing;
    }

    /**
     * @param list<string> $locales
     * @param list<string> $declaredKeys
     * @param list<string> $declaredPrefixes
     *
     * @return list<string>
     */
    private function unused(JsTranslationScan $scan, array $locales, array $declaredKeys, array $declaredPrefixes): array
    {
        $unused = [];

        foreach ($locales as $locale) {
            /** @var array<string, string> $messages */
            $messages = $this->translator->getCatalogue($locale)->all($this->domain);

            foreach (array_keys($messages) as $key) {
                if (!$scan->covers($key) && !\in_array($key, $declaredKeys, true) && !self::underAnyPrefix($key, $declaredPrefixes)) {
                    $unused[$key] = true;
                }
            }
        }

        $unused = array_keys($unused);
        sort($unused);

        return $unused;
    }

    /**
     * @param list<string> $prefixes
     */
    private static function underAnyPrefix(string $key, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ('' !== $prefix && str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
