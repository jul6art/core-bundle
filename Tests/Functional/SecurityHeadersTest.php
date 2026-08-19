<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Jul6Art\CoreBundle\EventListener\SecurityHeaderListener;
use Jul6Art\CoreBundle\Security\MathCaptchaService;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Wiring of the two response-level bricks. What a unit test cannot show: that the listener is
 * really absent from the container until it is switched on, and really hooked on
 * `kernel.response` once it is.
 */
#[CoversNothing]
final class SecurityHeadersTest extends AbstractFunctionalTestCase
{
    public function testTheListenerDoesNotEvenExistUntilItIsEnabled(): void
    {
        self::assertFalse($this->boot()->has(SecurityHeaderListener::class));
    }

    public function testEnablingItRegistersAndHooksIt(): void
    {
        $container = $this->boot('test', ['security_headers' => ['enabled' => true]]);

        self::assertTrue($container->has(SecurityHeaderListener::class));

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $hooked = false;
        foreach ($dispatcher->getListeners('kernel.response') as $listener) {
            if (\is_array($listener) && $listener[0] instanceof SecurityHeaderListener) {
                $hooked = true;
            }
        }

        self::assertTrue($hooked, 'The listener must run on kernel.response.');
    }

    /**
     * Late on purpose: the listener only fills headers the application did not set, so it has
     * to run after everything else has had its say.
     */
    public function testItRunsAfterTheApplicationsOwnListeners(): void
    {
        $container = $this->boot('test', ['security_headers' => ['enabled' => true]]);

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $priority = null;
        foreach ($dispatcher->getListeners('kernel.response') as $listener) {
            // The dispatcher compares listeners by identity, so it has to be handed back the
            // very callable it holds: a first-class callable built here is a different object
            // and yields null.
            if (\is_array($listener) && \is_callable($listener) && $listener[0] instanceof SecurityHeaderListener) {
                $priority = $dispatcher->getListenerPriority('kernel.response', $listener);
            }
        }

        self::assertSame(-100, $priority);
    }

    /** The captcha needs no optional package, so it is always available. */
    public function testTheCaptchaServiceIsAlwaysRegistered(): void
    {
        self::assertTrue($this->boot()->has(MathCaptchaService::class));
    }

    public function testTheCaptchaHonoursItsConfiguration(): void
    {
        $container = $this->boot('test', ['captcha' => ['operations' => ['*'], 'session_key' => '_wired']]);

        // The service reads the session off the request stack, so a functional check needs a
        // request in flight — exactly the situation a public form is in.
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/register');
        $request->setSession($session);

        $stack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push($request);

        $captcha = $container->get(MathCaptchaService::class);
        self::assertInstanceOf(MathCaptchaService::class, $captcha);

        self::assertMatchesRegularExpression('#^[1-9] \* [1-9]$#', $captcha->generate());
        self::assertTrue($session->has('_wired'), 'The configured session key must be the one used.');
    }
}
