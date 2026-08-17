<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Jul6Art\CoreBundle\Tests\Fixtures\Listener\ConcreteEntityListener;
use Jul6Art\CoreBundle\Tests\Fixtures\Listener\ConcreteEventListener;
use Jul6Art\CoreBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Boots a real kernel to prove Resources/config/services.yaml still wires against
 * services that exist in Symfony 7.4.
 */
#[CoversNothing]
final class ContainerTest extends AbstractFunctionalTestCase
{
    /**
     * Asserts identity against the container's own services, which is what proves the
     * `calls:` really resolved rather than just being type-correct.
     */
    public function testTheEntityListenerReceivesItsThreeDependencies(): void
    {
        $container = $this->boot();

        $listener = $container->get(TestKernel::ENTITY_LISTENER_ID);
        self::assertInstanceOf(ConcreteEntityListener::class, $listener);

        self::assertSame($container->get('request_stack'), $listener->requestStack());
        self::assertSame($container->get('security.token_storage'), $listener->tokenStorage());
        self::assertSame($container->get('translator'), $listener->translator());
    }

    public function testTheEventListenerReceivesTheTokenStorage(): void
    {
        $container = $this->boot();
        $listener = $container->get(TestKernel::EVENT_LISTENER_ID);

        self::assertInstanceOf(ConcreteEventListener::class, $listener);
        self::assertSame($container->get('security.token_storage'), $listener->tokenStorage());
    }

    /**
     * End to end proof that the flash bag rewrite works through the container: the
     * old "session.flash_bag" service no longer exists in Symfony 7.4.
     */
    public function testTheEntityListenerCanPushFlashesThroughTheRealSession(): void
    {
        $container = $this->boot();

        $listener = $container->get(TestKernel::ENTITY_LISTENER_ID);
        self::assertInstanceOf(ConcreteEntityListener::class, $listener);

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        $listener->addFlash('success', 'wired');

        self::assertSame(['wired'], $session->getFlashBag()->peek('success'));
    }

    public function testTheRemovedFlashBagServiceIsNotReferencedAnymore(): void
    {
        self::assertFalse($this->boot()->has('session.flash_bag'));
    }

    public function testTheConfigurationIsExposedAsContainerParameters(): void
    {
        $container = $this->boot('test', ['email_debug_title' => 'Custom']);

        self::assertFalse($container->getParameter('core.email_debug'));
        self::assertSame('Custom', $container->getParameter('core.email_debug_title'));
    }
}
