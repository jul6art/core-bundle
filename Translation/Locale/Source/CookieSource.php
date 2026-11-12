<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale\Source;

use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The language a visitor chose on an earlier visit, kept in a cookie past the end of the session
 * (`core.locale.cookie`). Written by {@see \Jul6Art\CoreBundle\Translation\Locale\LocalePreference::remember()}
 * when the choice is made, never on every response.
 */
final readonly class CookieSource implements LocaleSourceInterface
{
    public function __construct(
        private string $cookieName,
    ) {
    }

    #[\Override]
    public function locale(Request $request, ?UserInterface $user): ?string
    {
        $locale = $request->cookies->all()[$this->cookieName] ?? null;

        return \is_string($locale) ? $locale : null;
    }
}
