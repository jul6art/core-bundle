<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\AuditableTrait;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\CoreBundle\Entity\Traits\SoftDeletableTrait;
use Jul6Art\CoreBundle\Entity\Traits\TimestampableTrait;

/**
 * The three traits an entity of this ecosystem usually carries together, on one class — which is
 * also how the test proves they compose without clashing.
 *
 * `email` is UNIQUE on purpose: it is the column that makes soft delete awkward, and the reason
 * {@see SoftDeletableTrait::markDeletedValue()} exists.
 */
#[ORM\Entity]
#[ORM\Table(name: 'chronicle')]
#[ORM\UniqueConstraint(name: 'chronicle_email_unique', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class Chronicle
{
    use AuditableTrait;
    use IdTrait;
    use SoftDeletableTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 180)]
    private string $email;

    public function __construct(string $email = 'ada@example.test')
    {
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }
}
