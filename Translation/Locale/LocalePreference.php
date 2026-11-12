<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Where the language choice lives, and in which order it is read back (ADR-0001).
 *
 * **The order of the sources is the meaning** (`core.locale.sources`): an explicit choice made in this
 * session beats the one saved on the account, which beats what a link carries, which beats the
 * browser's guess. The first source that hands back a language the interface OFFERS wins; any other
 * value is ignored and the next source takes over — a stale session or a crafted link never breaks a
 * page. Nothing at all: `null`, and the application's default stays.
 */
final readonly class LocalePreference
{
    public const string SESSION_KEY = '_locale';

    /**
     * @param iterable<LocaleSourceInterface> $sources in the configured order
     */
    public function __construct(
        private SupportedLocales $locales,
        private iterable $sources,
        private PropertyAccessorInterface $propertyAccessor,
        private ?ManagerRegistry $doctrine = null,
        private string $accountProperty = 'locale',
        private string $cookieName = '',
        private int $cookieLifetime = 31536000,
    ) {
    }

    /**
     * @return non-empty-string|null
     */
    public function resolve(Request $request, ?UserInterface $user): ?string
    {
        foreach ($this->sources as $source) {
            $candidate = $source->locale($request, $user);

            if (null !== $candidate && '' !== $candidate && $this->locales->isSupported($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The choice just made: in the session (the visitor on the login page has nothing else), on the
     * account when it has the property — saved, so it follows the person to another device —, and in
     * the cookie when one is configured and a response is at hand.
     *
     * @throws \InvalidArgumentException for a language the interface does not offer: the caller checks first
     */
    public function remember(Request $request, ?UserInterface $user, string $locale, ?Response $response = null): void
    {
        if (!$this->locales->isSupported($locale)) {
            throw new \InvalidArgumentException(\sprintf('The interface does not offer "%s" (core.locale.supported).', $locale));
        }

        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, $locale);
        }

        if (null !== $user && $this->propertyAccessor->isWritable($user, $this->accountProperty)) {
            $this->propertyAccessor->setValue($user, $this->accountProperty, $locale);
            $this->doctrine?->getManagerForClass($user::class)?->flush();
        }

        if (null !== $response && '' !== $this->cookieName) {
            $response->headers->setCookie(Cookie::create(
                $this->cookieName,
                $locale,
                time() + $this->cookieLifetime,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                Cookie::SAMESITE_LAX,
            ));
        }
    }
}
