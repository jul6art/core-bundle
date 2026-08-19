<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
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

    /**
     * Id of the account that started an impersonation, or `null` when the session is a
     * genuine login.
     *
     * While `_switch_user` is active, Symfony wraps the original token inside the current one
     * as a {@see SwitchUserToken}. That wrapper is the only place the real actor survives:
     * {@see getCurrentUserIdOrNull()} returns the *impersonated* account, which is what most
     * code wants, and what makes an audit trail misleading if it stops there. Recording both
     * is what lets a reader tell "the user did this" from "an administrator did this as the
     * user".
     *
     * Same limitation as {@see getCurrentUserIdOrNull()}: only resolves for user classes that
     * actually declare an identifier.
     */
    public function getOriginalUserIdOrNull(): ?int
    {
        $token = $this->tokenStorage->getToken();

        if (!$token instanceof SwitchUserToken) {
            return null;
        }

        $original = $token->getOriginalToken()->getUser();

        if (!$original instanceof UserInterface || !method_exists($original, 'getId')) {
            return null;
        }

        $id = $original->getId();

        return is_numeric($id) ? (int) $id : null;
    }
}
