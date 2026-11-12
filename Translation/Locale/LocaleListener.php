<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Applies the chosen language to the request AND to the services that depend on it.
 *
 * ## Why priority 7, and why the rebroadcast
 *
 * Two constraints fix where this listener can run, and they contradict each other:
 *
 * 1. The firewall fills `TokenStorage` at priority **8**: anything that needs the account runs BELOW
 *    8, or the storage is still empty (a listener at 20 never sees the account's language).
 * 2. Symfony's `LocaleAwareListener` runs at priority **15** to broadcast `$request->getLocale()` to
 *    every `kernel.locale_aware` service — the translator first — and never comes back. A
 *    `setLocale()` after 15 leaves the translator on the default language: the session and the
 *    account are written, and the screen stays in English. That is the only symptom.
 *
 * Both hold by running at **7** and doing the broadcast again, exactly like
 * `LocaleAwareListener::setLocale()`.
 *
 * ## The not-found page
 *
 * An address the router does not know never reaches {@see onKernelRequest()}: the router fails first
 * (priority 32), and the not-found page spoke the default language under `/fr/…`. Before the error
 * controller duplicates the request, its language comes from the address, then from the visitor's
 * preference — without an account: the firewall has not run either.
 *
 * ## Remembering (`core.locale.remember`)
 *
 * - `route`: the address's language is written to a session that is ALREADY open — signing in at
 *   `/fr/connexion` then speaks French on `/2fa`. Never a session opened for it: a visitor's page
 *   would leave with a cookie.
 * - `query`: a `?<parameter>=` switch is an explicit choice, remembered like the selector's.
 */
final readonly class LocaleListener
{
    /**
     * @param iterable<LocaleAwareInterface> $localeAwareServices
     * @param list<string>                   $remember            `route` and/or `query`
     */
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private LocalePreference $preference,
        private SupportedLocales $locales,
        private iterable $localeAwareServices,
        private array $remember = [],
        private string $queryParameter = '',
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->tokenStorage->getToken()?->getUser();

        $this->rememberWhatTheRequestSays($request);

        $locale = $this->preference->resolve($request, $user instanceof UserInterface ? $user : null);
        if (null === $locale) {
            return;
        }

        $request->setLocale($locale);
        $this->broadcast($locale, $request->getDefaultLocale());
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->attributes->has('_route')) {
            return;
        }

        // The firewall opens the session, and it has not run: a visitor's session is opened here, or
        // the preference cannot read it.
        if ($request->hasPreviousSession()) {
            $request->getSession()->start();
        }

        $fromAddress = explode('/', ltrim($request->getPathInfo(), '/'))[0];
        $locale = $this->locales->isSupported($fromAddress) ? $fromAddress : $this->preference->resolve($request, null);
        if (null === $locale) {
            return;
        }

        $request->setLocale($locale);
        $this->broadcast($locale, $request->getDefaultLocale());
    }

    private function rememberWhatTheRequestSays(Request $request): void
    {
        if (\in_array('route', $this->remember, true) && $request->hasPreviousSession()) {
            $fromAddress = $request->attributes->get('_locale');
            if (\is_string($fromAddress) && $this->locales->isSupported($fromAddress)) {
                $request->getSession()->set(LocalePreference::SESSION_KEY, $fromAddress);
            }
        }

        if (\in_array('query', $this->remember, true) && '' !== $this->queryParameter && $request->hasSession()) {
            $fromLink = $request->query->all()[$this->queryParameter] ?? null;
            if (\is_string($fromLink) && $this->locales->isSupported($fromLink)) {
                $request->getSession()->set(LocalePreference::SESSION_KEY, $fromLink);
            }
        }
    }

    /**
     * Mirrors {@see \Symfony\Component\HttpKernel\EventListener\LocaleAwareListener::setLocale()}:
     * without it, only the request would know the new language, and the translator — which is what
     * makes the screen — would stay on the old one.
     */
    private function broadcast(string $locale, string $defaultLocale): void
    {
        foreach ($this->localeAwareServices as $service) {
            try {
                $service->setLocale($locale);
            } catch (\InvalidArgumentException) {
                $service->setLocale($defaultLocale);
            }
        }
    }
}
