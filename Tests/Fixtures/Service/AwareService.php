<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Service;

use Doctrine\ORM\EntityManagerInterface;
use Faker\Generator;
use Jul6Art\CoreBundle\Service\Traits\EntityManagerAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\EventDispatcherAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\FakerAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\FlashBagAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\TokenStorageAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\TranslatorAwareTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Uses every *AwareTrait shipped by the bundle and exposes what was injected, so
 * the traits are covered without reaching into protected state from the tests.
 */
class AwareService
{
    use EntityManagerAwareTrait;
    use EventDispatcherAwareTrait;
    use FakerAwareTrait;
    use FlashBagAwareTrait;
    use TokenStorageAwareTrait;
    use TranslatorAwareTrait;

    public function entityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function eventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function faker(): Generator
    {
        return $this->faker;
    }

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
