<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\CoreBundle\Tests\Fixtures\Repository\WidgetRepository;

#[ORM\Entity(repositoryClass: WidgetRepository::class)]
#[ORM\Table(name: 'widget')]
class Widget
{
    use IdTrait;

    #[ORM\Column(length: 255)]
    private string $name;

    public function __construct(string $name = 'widget')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
