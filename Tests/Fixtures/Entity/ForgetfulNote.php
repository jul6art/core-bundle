<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\CreatedAtTrait;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Deliberately **missing** `#[ORM\HasLifecycleCallbacks]`, so the trap the traits' docblocks
 * warn about is exercised rather than merely described. Doctrine ignores `#[ORM\PrePersist]`
 * without that attribute, and says nothing about it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'forgetful_note')]
class ForgetfulNote
{
    use CreatedAtTrait;
    use IdTrait;

    #[ORM\Column(length: 255)]
    private string $body;

    public function __construct(string $body = 'forgetful')
    {
        $this->body = $body;
    }
}
