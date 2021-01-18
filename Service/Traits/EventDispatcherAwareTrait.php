<?php

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Trait EventDispatcherAwareTrait.
 */
trait EventDispatcherAwareTrait
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @required
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
}
