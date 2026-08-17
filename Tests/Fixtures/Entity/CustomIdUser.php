<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user exposing an arbitrary getId() return value, to cover every branch of
 * TokenStorageAwareTrait::getCurrentUserIdOrNull().
 */
class CustomIdUser implements UserInterface
{
    public function __construct(private readonly mixed $id)
    {
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return 'custom-id-user';
    }

    /**
     * Still required by UserInterface in Symfony 7.4, deprecated since 7.3.
     */
    public function eraseCredentials(): void
    {
    }
}
