<?php

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Class FlashBagAwareTrait
 */
trait FlashBagAwareTrait
{
    /**
     * @var FlashBagInterface
     */
    protected $flashBag;

    /**
     * @required
     */
    public function setFlashBag(FlashBagInterface $flashBag): void
    {
        $this->flashBag = $flashBag;
    }
}
