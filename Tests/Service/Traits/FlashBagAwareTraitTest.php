<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Service\Traits;

use Jul6Art\CoreBundle\Service\Traits\FlashBagAwareTrait;
use Jul6Art\CoreBundle\Tests\Fixtures\Service\AwareService;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The flash bag is no longer injected: "session.flash_bag" was removed in Symfony
 * 6.0, so it is now read from the request stack.
 */
#[CoversTrait(FlashBagAwareTrait::class)]
final class FlashBagAwareTraitTest extends TestCase
{
    public function testItReadsTheFlashBagFromTheCurrentSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $service = $this->serviceWithSession($session);

        self::assertSame($session->getFlashBag(), $service->getFlashBag());
    }

    public function testAddFlashPushesOntoTheBag(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $service = $this->serviceWithSession($session);

        $service->addFlash('success', 'saved');
        $service->addFlash('success', 'again');
        $service->addFlash('error', 'nope');

        self::assertSame(['saved', 'again'], $session->getFlashBag()->peek('success'));
        self::assertSame(['nope'], $session->getFlashBag()->peek('error'));
    }

    public function testAddFlashAcceptsNonStringMessages(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $service = $this->serviceWithSession($session);

        $service->addFlash('notice', ['a', 'b']);

        self::assertSame([['a', 'b']], $session->getFlashBag()->peek('notice'));
    }

    public function testItFailsWhenThereIsNoSession(): void
    {
        $service = new AwareService();
        $requestStack = new RequestStack([Request::create('/')]);
        $service->setRequestStack($requestStack);

        $this->expectException(SessionNotFoundException::class);

        $service->getFlashBag();
    }

    public function testItFailsWhenThereIsNoRequestAtAll(): void
    {
        $service = new AwareService();
        $service->setRequestStack(new RequestStack());

        $this->expectException(SessionNotFoundException::class);

        $service->getFlashBag();
    }

    public function testItFailsWhenTheSessionCarriesNoFlashBag(): void
    {
        $service = $this->serviceWithSession(self::createStub(SessionInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('must implement');

        $service->getFlashBag();
    }

    private function serviceWithSession(SessionInterface $session): AwareService
    {
        $request = Request::create('/');
        $request->setSession($session);

        $requestStack = new RequestStack([$request]);

        $service = new AwareService();
        $service->setRequestStack($requestStack);

        return $service;
    }
}
