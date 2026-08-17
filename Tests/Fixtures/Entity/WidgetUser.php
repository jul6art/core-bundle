<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user carrying an identifier, as TokenStorageAwareTrait expects.
 */
class WidgetUser implements UserInterface
{
    use IdTrait;

    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return 'widget-user';
    }

    /**
     * Still required by UserInterface in Symfony 7.4, deprecated since 7.3.
     */
    public function eraseCredentials(): void
    {
    }
}
