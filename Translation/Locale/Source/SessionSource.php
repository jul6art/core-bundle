<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale\Source;

use Jul6Art\CoreBundle\Translation\Locale\LocalePreference;
use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The choice made in this session — the only channel an anonymous visitor has, on the login page.
 *
 * Read from a session that is already open, or that the browser brought back (a session cookie):
 * reading never opens a new one, so a visitor's page never leaves with a cookie it did not have.
 *
 * ⚠️ Never on a stateless route (`_stateless`, an API): Symfony refuses a session used there ("Session
 * was used while the request was declared stateless"), and the browser's session cookie travels with
 * the API calls of a page all the same.
 */
final readonly class SessionSource implements LocaleSourceInterface
{
    #[\Override]
    public function locale(Request $request, ?UserInterface $user): ?string
    {
        if (!$request->hasSession() || $request->attributes->getBoolean('_stateless') || (!$request->getSession()->isStarted() && !$request->hasPreviousSession())) {
            return null;
        }

        $locale = $request->getSession()->get(LocalePreference::SESSION_KEY);

        return \is_string($locale) ? $locale : null;
    }
}
