<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Controller;

use Jul6Art\CoreBundle\Controller\LocaleController;
use Jul6Art\CoreBundle\Translation\Locale\LocalePreference;
use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

/**
 * The switch's contract with admin-bundle's `ui--locale-switcher`: the language in the BODY, a CSRF
 * token, 204 (ADR-0001).
 */
#[CoversClass(LocaleController::class)]
final class LocaleControllerTest extends TestCase
{
    public function testAValidSwitchIsRememberedAndAnswers204(): void
    {
        [$controller, $request] = $this->switch(['locale' => 'fr'], valid: true);

        $response = $controller($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('fr', $request->getSession()->get(LocalePreference::SESSION_KEY));
    }

    public function testAnInvalidTokenIsRefusedWithAnHttp403NotASecurityException(): void
    {
        [$controller, $request] = $this->switch(['locale' => 'fr'], valid: false);

        $this->expectException(AccessDeniedHttpException::class);
        $controller($request);
    }

    public function testAnUnofferedLanguageIsNotFound(): void
    {
        [$controller, $request] = $this->switch(['locale' => 'pt'], valid: true);

        $this->expectException(NotFoundHttpException::class);
        $controller($request);
    }

    public function testALanguageInTheAddressInsteadOfTheBodyIsNotASwitch(): void
    {
        [$controller, $request] = $this->switch([], valid: true);

        $this->expectException(NotFoundHttpException::class);
        $controller($request);
    }

    public function testWithoutCsrfProtectionTheSwitchSaysWhy(): void
    {
        $locales = new SupportedLocales(['en', 'fr'], 'en');
        $controller = new LocaleController(new LocalePreference($locales, [], PropertyAccess::createPropertyAccessor()), $locales, new TokenStorage());

        $this->expectException(\LogicException::class);
        $controller(Request::create('/locale', 'POST', ['locale' => 'fr', '_token' => 'x']));
    }

    /**
     * @param array<string, string> $body
     *
     * @return array{LocaleController, Request}
     */
    private function switch(array $body, bool $valid): array
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/locale', 'POST');
        $request->setSession($session);
        $stack = new RequestStack([$request]);
        $csrf = new CsrfTokenManager(null, new SessionTokenStorage($stack));

        $request->request->add($body + ['_token' => $valid ? $csrf->getToken(LocaleController::CSRF_TOKEN_ID)->getValue() : 'forged']);
        $locales = new SupportedLocales(['en', 'fr'], 'en');

        return [new LocaleController(new LocalePreference($locales, [], PropertyAccess::createPropertyAccessor()), $locales, new TokenStorage(), $csrf), $request];
    }
}
