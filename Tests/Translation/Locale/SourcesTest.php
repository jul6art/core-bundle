<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation\Locale;

use Jul6Art\CoreBundle\Translation\Locale\LocalePreference;
use Jul6Art\CoreBundle\Translation\Locale\Source\AcceptLanguageSource;
use Jul6Art\CoreBundle\Translation\Locale\Source\AccountSource;
use Jul6Art\CoreBundle\Translation\Locale\Source\CookieSource;
use Jul6Art\CoreBundle\Translation\Locale\Source\QuerySource;
use Jul6Art\CoreBundle\Translation\Locale\Source\RouteSource;
use Jul6Art\CoreBundle\Translation\Locale\Source\SessionSource;
use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Each place a language can come from hands back a raw candidate — or nothing (ADR-0001).
 */
#[CoversClass(RouteSource::class)]
#[CoversClass(SessionSource::class)]
#[CoversClass(AccountSource::class)]
#[CoversClass(QuerySource::class)]
#[CoversClass(CookieSource::class)]
#[CoversClass(AcceptLanguageSource::class)]
#[CoversClass(SupportedLocales::class)]
final class SourcesTest extends TestCase
{
    public function testTheRouteSourceReadsTheAddressLanguage(): void
    {
        $request = Request::create('/fr/page');
        self::assertNull(new RouteSource()->locale($request, null));

        $request->attributes->set('_locale', 'fr');
        self::assertSame('fr', new RouteSource()->locale($request, null));
    }

    public function testTheSessionSourceReadsAnOpenSessionAndNeverOpensOne(): void
    {
        $request = Request::create('/');
        self::assertNull(new SessionSource()->locale($request, null), 'No session at all.');

        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        self::assertNull(new SessionSource()->locale($request, null), 'A session neither open nor brought back.');
        self::assertFalse($session->isStarted(), 'Reading must not open it.');

        $session->set(LocalePreference::SESSION_KEY, 'fr');
        self::assertSame('fr', new SessionSource()->locale($request, null), 'An open session is read.');
    }

    public function testTheSessionSourceReadsASessionTheBrowserBroughtBack(): void
    {
        $storage = new MockArraySessionStorage();
        $storage->setSessionData(['_sf2_attributes' => [LocalePreference::SESSION_KEY => 'fr']]);
        $request = Request::create('/', cookies: ['MOCKSESSID' => 'abc']);
        $request->setSession(new Session($storage));

        self::assertSame('fr', new SessionSource()->locale($request, null));
    }

    public function testTheSessionSourceLeavesAStatelessRouteAlone(): void
    {
        $storage = new MockArraySessionStorage();
        $storage->setSessionData(['_sf2_attributes' => [LocalePreference::SESSION_KEY => 'fr']]);
        $session = new Session($storage);
        $request = Request::create('/api/jobs', cookies: ['MOCKSESSID' => 'abc']);
        $request->setSession($session);
        $request->attributes->set('_stateless', true);

        self::assertNull(new SessionSource()->locale($request, null));
        self::assertFalse($session->isStarted(), 'Symfony refuses a session used on a stateless route.');
    }

    public function testTheAccountSourceReadsTheConfiguredProperty(): void
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $account = new class implements UserInterface {
            public string $preferredLocale = 'fr';

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function getUserIdentifier(): string
            {
                return 'ada';
            }

            public function eraseCredentials(): void
            {
            }
        };

        self::assertSame('fr', new AccountSource($accessor, 'preferredLocale')->locale(Request::create('/'), $account));
        self::assertNull(new AccountSource($accessor)->locale(Request::create('/'), $account), 'No `locale` property: no say.');
        self::assertNull(new AccountSource($accessor)->locale(Request::create('/'), new InMemoryUser('machine', null)));
        self::assertNull(new AccountSource($accessor)->locale(Request::create('/'), null));
    }

    public function testTheQuerySourceReadsAStringAndNothingElse(): void
    {
        self::assertSame('nl', new QuerySource('lang')->locale(Request::create('/?lang=nl'), null));
        self::assertNull(new QuerySource('lang')->locale(Request::create('/'), null));
        // `?lang[]=de` must be ignored, not turned into a 400 by InputBag::get().
        self::assertNull(new QuerySource('lang')->locale(Request::create('/?lang[]=de'), null));
    }

    public function testTheCookieSourceReadsItsCookie(): void
    {
        self::assertSame('fr', new CookieSource('user_locale')->locale(Request::create('/', cookies: ['user_locale' => 'fr']), null));
        self::assertNull(new CookieSource('user_locale')->locale(Request::create('/'), null));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function acceptLanguages(): iterable
    {
        yield 'a region still speaks the language' => ['fr-BE,fr;q=0.9', 'fr'];
        yield 'the underscore form too' => ['en_US', 'en'];
        yield 'the preferred offered one wins' => ['de-DE,nl;q=0.8,fr;q=0.5', 'nl'];
        yield 'nothing offered: null, never the first of the list' => ['pt-BR,es;q=0.8', null];
        yield 'no header' => ['', null];
    }

    #[DataProvider('acceptLanguages')]
    public function testTheAcceptLanguageSourceComparesThePrimarySubtag(string $header, ?string $expected): void
    {
        // Request::create() announces `en-us` by default: the header is set, or removed, explicitly.
        $request = Request::create('/');
        '' !== $header ? $request->headers->set('Accept-Language', $header) : $request->headers->remove('Accept-Language');

        self::assertSame($expected, new AcceptLanguageSource(new SupportedLocales(['en', 'fr', 'nl'], 'en'))->locale($request, null));
    }

    public function testTheOfferedListFallsBackToTheDefaultAlone(): void
    {
        $locales = new SupportedLocales([], 'fr');

        self::assertSame(['fr'], $locales->all());
        self::assertSame('fr', $locales->default());
        self::assertTrue($locales->isSupported('fr'));
        self::assertFalse($locales->isSupported('en'));
        self::assertSame(['en', 'fr'], new SupportedLocales(['en', 'fr', 'en', ''], 'en')->all());
    }
}
