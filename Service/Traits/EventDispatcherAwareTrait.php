<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait EventDispatcherAwareTrait.
 */
trait EventDispatcherAwareTrait
{
    protected EventDispatcherInterface $eventDispatcher;

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
}
