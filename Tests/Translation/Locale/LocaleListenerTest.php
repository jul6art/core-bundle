<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation\Locale;

use Jul6Art\CoreBundle\Translation\Locale\LocaleListener;
use Jul6Art\CoreBundle\Translation\Locale\LocalePreference;
use Jul6Art\CoreBundle\Translation\Locale\Source\QuerySource;
use Jul6Art\CoreBundle\Translation\Locale\Source\RouteSource;
use Jul6Art\CoreBundle\Translation\Locale\Source\SessionSource;
use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The language reaches the request AND the translator; the not-found page speaks it; `remember`
 * keeps what the request says without opening a session for it (ADR-0001).
 */
#[CoversClass(LocaleListener::class)]
final class LocaleListenerTest extends TestCase
{
    private LocaleAwareInterface $translator;

    #[\Override]
    protected function setUp(): void
    {
        $this->translator = new class implements LocaleAwareInterface {
            public string $locale = 'en';

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };
    }

    public function testTheChosenLanguageReachesTheRequestAndIsRebroadcast(): void
    {
        $request = $this->requestWithSession('/admin', ['_locale' => 'fr']);

        $this->listener()->onKernelRequest($this->event($request));

        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $this->translator->getLocale(), 'Without the rebroadcast, the screen stays in the default language.');
    }

    public function testNothingChosenLeavesEverythingAlone(): void
    {
        $request = Request::create('/admin');

        $this->listener()->onKernelRequest($this->event($request));

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $this->translator->getLocale());
    }

    public function testASubRequestIsIgnored(): void
    {
        $request = $this->requestWithSession('/fragment', ['_locale' => 'fr']);

        $this->listener()->onKernelRequest(new RequestEvent($this->kernel(), $request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame('en', $this->translator->getLocale());
    }

    public function testTheNotFoundPageSpeaksTheAddressLanguage(): void
    {
        $request = Request::create('/fr/nowhere');

        $this->listener()->onKernelException($this->exception($request));

        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $this->translator->getLocale());
    }

    public function testTheNotFoundPageFallsBackToTheVisitorsPreference(): void
    {
        $request = $this->requestWithSession('/nowhere', ['_locale' => 'fr']);

        $this->listener()->onKernelException($this->exception($request));

        self::assertSame('fr', $this->translator->getLocale());
    }

    public function testAMatchedRouteIsLeftToTheRequestListener(): void
    {
        $request = Request::create('/fr/nowhere');
        $request->attributes->set('_route', 'somewhere');

        $this->listener()->onKernelException($this->exception($request));

        self::assertSame('en', $this->translator->getLocale());
    }

    public function testTheAddressLanguageIsRememberedInAnOpenSessionOnly(): void
    {
        $withSession = $this->requestWithSession('/fr/page', [], cookie: true);
        $withSession->attributes->set('_locale', 'fr');
        $this->listener(remember: ['route'])->onKernelRequest($this->event($withSession));
        self::assertSame('fr', $withSession->getSession()->get(LocalePreference::SESSION_KEY));

        $session = new Session(new MockArraySessionStorage());
        $visitor = Request::create('/fr/page');
        $visitor->setSession($session);
        $visitor->attributes->set('_locale', 'fr');
        $this->listener(remember: ['route'])->onKernelRequest($this->event($visitor));
        self::assertFalse($session->isStarted(), 'A visitor\'s page never leaves with a session it did not have.');
        self::assertSame('fr', $visitor->getLocale(), 'The address still decides the page.');
    }

    public function testAnExplicitSwitchByLinkIsRemembered(): void
    {
        $request = Request::create('/page?_locale=fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->listener(remember: ['query'], queryParameter: '_locale')->onKernelRequest($this->event($request));
        self::assertSame('fr', $request->getSession()->get(LocalePreference::SESSION_KEY));
        self::assertSame('fr', $request->getLocale());

        $next = Request::create('/other');
        $next->setSession($request->getSession());
        $this->listener(remember: ['query'], queryParameter: '_locale')->onKernelRequest($this->event($next));
        self::assertSame('fr', $next->getLocale(), 'The next page keeps it.');
    }

    /**
     * @param list<string> $remember
     */
    private function listener(array $remember = [], string $queryParameter = ''): LocaleListener
    {
        $locales = new SupportedLocales(['en', 'fr'], 'en');
        $sources = [new RouteSource(), new SessionSource()];
        if ('' !== $queryParameter) {
            array_unshift($sources, new QuerySource($queryParameter));
        }

        return new LocaleListener(
            new TokenStorage(),
            new LocalePreference($locales, $sources, PropertyAccess::createPropertyAccessor()),
            $locales,
            [$this->translator],
            $remember,
            $queryParameter,
        );
    }

    /**
     * @param array<string, string> $data
     */
    private function requestWithSession(string $path, array $data, bool $cookie = false): Request
    {
        $session = new Session(new MockArraySessionStorage());
        foreach ($data as $key => $value) {
            $session->set($key, $value);
        }
        $request = Request::create($path, cookies: $cookie ? [$session->getName() => 'abc'] : []);
        $request->setSession($session);

        return $request;
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function exception(Request $request): ExceptionEvent
    {
        return new ExceptionEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, new NotFoundHttpException());
    }

    private function kernel(): HttpKernelInterface
    {
        return self::createStub(HttpKernelInterface::class);
    }
}
