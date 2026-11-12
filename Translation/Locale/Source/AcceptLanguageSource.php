<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale\Source;

use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The browser's best guess (`Accept-Language`): the first language it announces that the interface
 * offers.
 *
 * ⚠️ **The PRIMARY subtag is compared.** A Belgian browser announces `fr-BE`, a Canadian `fr-CA`, an
 * American `en-US`: all of them speak a language the interface offers, and comparing whole tags would
 * send them to the default. Symfony normalises the header to underscores (`fr_BE`): both separators
 * are cut.
 *
 * ⚠️ **`null` when nothing matches — never `getPreferredLanguage()`**, which always answers: handed a
 * list, it falls back to its first entry, and every source after this one (a country, say) would be
 * unreachable.
 */
final readonly class AcceptLanguageSource implements LocaleSourceInterface
{
    public function __construct(
        private SupportedLocales $locales,
    ) {
    }

    #[\Override]
    public function locale(Request $request, ?UserInterface $user): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(explode('-', str_replace('_', '-', $language))[0]);

            if ('' !== $primary && $this->locales->isSupported($primary)) {
                return $primary;
            }
        }

        return null;
    }
}
