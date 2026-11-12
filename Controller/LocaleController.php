<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Controller;

use Jul6Art\CoreBundle\Translation\Locale\LocalePreference;
use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The language switch (ADR-0001): the contract of admin-bundle's `ui--locale-switcher` Stimulus
 * controller, which POSTs `locale` and `_token` as `FormData` to a FIXED address and reloads the page
 * itself — hence 204, and the language read from the BODY. A `/locale/{locale}` route compiled,
 * answered 204 and switched back to the current language: a perfect no-op only the screen showed.
 *
 * **The bundle declares no route**: the application does, under the name its templates use —
 *
 * ```yaml
 * admin_locale_switch:
 *     path: /locale
 *     controller: Jul6Art\CoreBundle\Controller\LocaleController
 *     methods: [POST]
 * ```
 *
 * — and may declare more than one: the account written is the CURRENT firewall's, so a customer
 * portal on its own firewall routes its own address to the same controller.
 *
 * ⚠️ Deliberately PUBLIC: one picks a language BEFORE having an account, on the login page. The
 * application's `access_control` says so.
 *
 * ⚠️ `AccessDeniedHttpException` (HTTP), never the security exception: on a public route, the firewall
 * turns the latter into a redirection to the login page for an anonymous visitor — an invalid token
 * would answer "sign in" instead of "refused".
 */
final readonly class LocaleController
{
    public const string CSRF_TOKEN_ID = 'locale_switch';

    public function __construct(
        private LocalePreference $preference,
        private SupportedLocales $locales,
        private TokenStorageInterface $tokenStorage,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $csrf = $this->csrfTokenManager ?? throw new \LogicException('The language switch checks a CSRF token: enable framework.csrf_protection.');
        $token = $request->request->all()['_token'] ?? null;
        if (!\is_string($token) || !$csrf->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new AccessDeniedHttpException();
        }

        $locale = $request->request->all()['locale'] ?? null;
        if (!\is_string($locale) || !$this->locales->isSupported($locale)) {
            throw new NotFoundHttpException();
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $this->preference->remember($request, $user instanceof UserInterface ? $user : null, $locale, $response);

        return $response;
    }
}
