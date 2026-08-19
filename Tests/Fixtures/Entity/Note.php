<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\CreatedAtTrait;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * The lightweight variant: `created_at` and nothing else.
 */
#[ORM\Entity]
#[ORM\Table(name: 'note')]
#[ORM\HasLifecycleCallbacks]
class Note
{
    use CreatedAtTrait;
    use IdTrait;

    #[ORM\Column(length: 255)]
    private string $body;

    public function __construct(string $body = 'note')
    {
        $this->body = $body;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }
}
