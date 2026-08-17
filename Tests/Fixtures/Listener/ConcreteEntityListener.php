<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Listener;

use Jul6Art\CoreBundle\EntityListener\AbstractEntityListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Exposes what the container injected into the abstract parent definition.
 */
class ConcreteEntityListener extends AbstractEntityListener
{
    public function requestStack(): RequestStack
    {
        return $this->requestStack;
    }

    public function tokenStorage(): TokenStorageInterface
    {
        return $this->tokenStorage;
    }

    public function translator(): TranslatorInterface
    {
        return $this->translator;
    }
}
