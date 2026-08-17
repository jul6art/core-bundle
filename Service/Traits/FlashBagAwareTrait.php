<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Class FlashBagAwareTrait.
 *
 * The "session.flash_bag" service was removed in Symfony 6.0, so the flash bag is
 * now resolved from the current request's session instead of being injected.
 */
trait FlashBagAwareTrait
{
    protected RequestStack $requestStack;

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @throws SessionNotFoundException if there is no active session
     * @throws \LogicException          if the session does not carry a flash bag
     */
    public function getFlashBag(): FlashBagInterface
    {
        $session = $this->requestStack->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            throw new \LogicException(\sprintf('The session must implement "%s" to use flash messages.', FlashBagAwareSessionInterface::class));
        }

        return $session->getFlashBag();
    }

    public function addFlash(string $type, mixed $message): void
    {
        $this->getFlashBag()->add($type, $message);
    }
}
