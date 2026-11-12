<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An account that keeps its interface language, as the applications' `User` does (ADR-0001).
 */
#[ORM\Entity]
class LocaleAccount implements UserInterface
{
    use IdTrait;

    #[ORM\Column(length: 5)]
    private string $locale = 'en';

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $email,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    #[\Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @return non-empty-string
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : 'locale-account';
    }

    /**
     * Still required by UserInterface in Symfony 7.4, deprecated since 7.3.
     */
    public function eraseCredentials(): void
    {
    }
}
