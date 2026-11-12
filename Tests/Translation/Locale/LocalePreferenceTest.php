<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation\Locale;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Jul6Art\CoreBundle\Translation\Locale\LocalePreference;
use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The order is the meaning, an unoffered value is skipped, and a choice is remembered everywhere it
 * can be (ADR-0001).
 */
#[CoversClass(LocalePreference::class)]
final class LocalePreferenceTest extends TestCase
{
    public function testTheFirstOfferedCandidateWinsInTheConfiguredOrder(): void
    {
        $preference = $this->preference([$this->source('nl'), $this->source('fr'), $this->source('en')]);

        self::assertSame('fr', $preference->resolve(Request::create('/'), null), 'nl is not offered: skipped, not returned.');
    }

    public function testNothingOfferedLeavesTheDefaultToTheApplication(): void
    {
        self::assertNull($this->preference([$this->source(null), $this->source('pt'), $this->source('')])->resolve(Request::create('/'), null));
    }

    public function testASourceAfterAWinnerIsNeverAsked(): void
    {
        $never = new class implements LocaleSourceInterface {
            public function locale(Request $request, ?UserInterface $user): ?string
            {
                throw new \LogicException('Asked after a winner.');
            }
        };

        self::assertSame('en', $this->preference([$this->source('en'), $never])->resolve(Request::create('/'), null));
    }

    public function testRememberingWritesTheSessionTheAccountAndTheCookie(): void
    {
        $account = $this->account();
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('flush');
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($manager);

        $request = Request::create('https://app.test/locale');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = new Response();
        $this->preference([], $doctrine, cookie: 'user_locale')->remember($request, $account, 'fr', $response);

        self::assertSame('fr', $request->getSession()->get(LocalePreference::SESSION_KEY));
        self::assertSame('fr', $account->locale, 'The account follows the person to another device.');
        $cookie = $response->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie);
        self::assertSame(['user_locale', 'fr'], [$cookie->getName(), $cookie->getValue()]);
        self::assertTrue($cookie->isSecure(), 'Secure on an HTTPS request.');
        self::assertTrue($cookie->isHttpOnly());
        self::assertGreaterThan(time() + 86400 * 300, $cookie->getExpiresTime(), 'A year by default.');
    }

    public function testWithoutACookieNameNoCookieIsWritten(): void
    {
        $response = new Response();
        $this->preference([])->remember(Request::create('/'), null, 'fr', $response);

        self::assertSame([], $response->headers->getCookies());
    }

    public function testAnAccountWithoutThePropertyIsLeftAlone(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::never())->method('getManagerForClass');

        $this->preference([], $doctrine)->remember(Request::create('/'), new InMemoryUser('machine', null), 'fr');
    }

    public function testAnUnofferedLanguageIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->preference([])->remember(Request::create('/'), null, 'pt');
    }

    /**
     * @param list<LocaleSourceInterface> $sources
     */
    private function preference(array $sources, ?ManagerRegistry $doctrine = null, string $cookie = ''): LocalePreference
    {
        return new LocalePreference(new SupportedLocales(['en', 'fr'], 'en'), $sources, PropertyAccess::createPropertyAccessor(), $doctrine, 'locale', $cookie);
    }

    private function source(?string $locale): LocaleSourceInterface
    {
        return new readonly class($locale) implements LocaleSourceInterface {
            public function __construct(private ?string $value)
            {
            }

            public function locale(Request $request, ?UserInterface $user): ?string
            {
                return $this->value;
            }
        };
    }

    /**
     * @return UserInterface&object{locale: string}
     */
    private function account(): UserInterface
    {
        return new class implements UserInterface {
            public string $locale = 'en';

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
    }
}
