<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait TokenStorageAwareTrait.
 */
trait TokenStorageAwareTrait
{
    protected TokenStorageInterface $tokenStorage;

    #[Required]
    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * UserInterface does not expose an identifier, so this only resolves for user
     * classes that actually declare one - typically through IdTrait.
     */
    public function getCurrentUserIdOrNull(): ?int
    {
        $user = $this->getCurrentUserOrNull();

        if (null === $user || !method_exists($user, 'getId')) {
            return null;
        }

        $id = $user->getId();

        return is_numeric($id) ? (int) $id : null;
    }

    public function getCurrentUserOrNull(): ?UserInterface
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
