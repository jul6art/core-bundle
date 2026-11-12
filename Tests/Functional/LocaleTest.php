<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\CoreBundle\Controller\LocaleController;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\LocaleAccount;
use Jul6Art\CoreBundle\Tests\Fixtures\Translation\CountryLocaleSource;
use Jul6Art\CoreBundle\Translation\Locale\LocaleListener;
use Jul6Art\CoreBundle\Translation\Locale\LocalePreference;
use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The interface language as an application wires it (ADR-0001): off by default, the kernel's
 * locales by default, the not-found page through the real kernel, the switch with the real CSRF
 * manager and an account saved in the database, an application's own source at its rank.
 */
#[CoversClass(LocaleListener::class)]
#[CoversClass(LocalePreference::class)]
#[CoversClass(LocaleController::class)]
final class LocaleTest extends AbstractFunctionalTestCase
{
    public function testOffByDefaultNothingIsRegistered(): void
    {
        $container = $this->boot();

        self::assertFalse($container->has(LocalePreference::class), 'An application keeps its own listener until it turns this on.');
        self::assertFalse($container->has(LocaleController::class));
    }

    public function testOnTheOfferedLanguagesAreTheKernelsByDefault(): void
    {
        $container = $this->boot('test', ['locale' => ['enabled' => true]]);

        $locales = $container->get(SupportedLocales::class);
        self::assertInstanceOf(SupportedLocales::class, $locales);
        self::assertSame(['en', 'fr'], $locales->all());
        self::assertSame('en', $locales->default());
        self::assertTrue($container->has(LocaleController::class), 'The switch exists; the application routes to it.');
    }

    public function testTheNotFoundPageSpeaksTheAddressLanguageThroughTheKernel(): void
    {
        $container = $this->boot('test', ['locale' => ['enabled' => true]]);
        $kernel = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        // The translator's language WHILE the error page is answered: Symfony resets it once the
        // request finishes (`kernel.finish_request`), so it is read on the way out.
        $translator = $this->translator($container);
        $heard = [];
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(KernelEvents::RESPONSE, static function () use ($translator, &$heard): void {
            $heard[] = $translator->getLocale();
        });

        $request = Request::create('/fr/nowhere');
        $response = $kernel->handle($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $heard[0] ?? null, 'The error page is answered in French: the translator heard it, not only the request.');
    }

    public function testTheSwitchSavesTheAccountsLanguageInTheDatabase(): void
    {
        $container = $this->boot('test', ['locale' => ['enabled' => true]], withOrm: true);
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(LocaleAccount::class)]);
        $account = new LocaleAccount('ada@example.test');
        $entityManager->persist($account);
        $entityManager->flush();

        $tokens = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken($account, 'main', ['ROLE_USER']));
        $request = Request::create('/locale', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push($request);
        $csrf = $container->get('security.csrf.token_manager');
        self::assertInstanceOf(CsrfTokenManagerInterface::class, $csrf);
        $request->request->add(['locale' => 'fr', '_token' => $csrf->getToken(LocaleController::CSRF_TOKEN_ID)->getValue()]);

        $controller = $container->get(LocaleController::class);
        self::assertInstanceOf(LocaleController::class, $controller);
        $response = $controller($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('fr', $request->getSession()->get(LocalePreference::SESSION_KEY));
        $entityManager->clear();
        self::assertSame('fr', $entityManager->find(LocaleAccount::class, $account->getId())?->getLocale(), 'Saved, not only set: it follows the person to another device.');
    }

    public function testAnApplicationsSourceTakesItsRank(): void
    {
        $container = $this->boot('test', ['locale' => [
            'enabled' => true,
            'sources' => ['session', 'accept_language', CountryLocaleSource::class],
        ]]);
        $preference = $container->get(LocalePreference::class);
        self::assertInstanceOf(LocalePreference::class, $preference);

        $belgian = Request::create('/');
        $belgian->headers->set('Accept-Language', 'pt-BR');
        $belgian->headers->set('CF-IPCountry', 'BE');
        self::assertSame('fr', $preference->resolve($belgian, null), 'No offered language announced: the country decides.');

        // A new request: `getLanguages()` keeps what it read the first time.
        $briton = Request::create('/');
        $briton->headers->set('Accept-Language', 'en-GB');
        $briton->headers->set('CF-IPCountry', 'BE');
        self::assertSame('en', $preference->resolve($briton, null), 'The browser, ranked before the country, wins.');
    }

    public function testAQuerySourceWithoutItsParameterIsAConfigurationError(): void
    {
        $this->expectExceptionMessageIsOrContains('core.locale.query_parameter');

        $this->boot('test', ['locale' => ['enabled' => true, 'sources' => ['query'], 'query_parameter' => '']]);
    }

    private function translator(ContainerInterface $container): LocaleAwareInterface
    {
        $translator = $container->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);

        return $translator;
    }
}
