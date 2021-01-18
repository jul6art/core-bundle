<?php

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Trait TokenStorageAwareTrait.
 */
trait TokenStorageAwareTrait
{
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @required
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return int|null
     */
    public function getCurrentUserIdOrNull(): ?int
    {
        $user = $this->getCurrentUserOrNull();

        if ($user instanceof UserInterface) {
            return $user->getId();
        }

        return null;
    }

    /**
     * @return UserInterface|null
     */
    public function getCurrentUserOrNull(): ?UserInterface
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface) {
            $user = $token->getUser();

            if ($user instanceof UserInterface) {
                return $user;
            }
        }

        return null;
    }
}
