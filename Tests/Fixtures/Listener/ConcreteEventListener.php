<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Listener;

use Jul6Art\CoreBundle\EventListener\AbstractEventListener;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Exposes what the container injected into the abstract parent definition.
 */
class ConcreteEventListener extends AbstractEventListener
{
    public function tokenStorage(): TokenStorageInterface
    {
        return $this->tokenStorage;
    }
}
