<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;

/**
 * Child entity used by the soft-delete fixtures: it carries everything those bricks need
 * to be exercised against a real database — a nullable FK to its parent, a `deletedAt`
 * marker, a UNIQUE-ish text column the cascade helper suffixes, and a JSON column for the
 * `JSON_TEXT()` DQL function.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gadget')]
class Gadget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Widget::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Widget $widget = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    private ?string $secret = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWidget(): ?Widget
    {
        return $this->widget;
    }

    public function setWidget(?Widget $widget): static
    {
        $this->widget = $widget;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param list<string> $tags */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }
}
