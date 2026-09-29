<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\MisconfiguredEntity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;

/** ⚠️ Asks for bulk while a remove callback cleans up after it (a file on disk, say): the purge must REFUSE. */
#[ORM\Entity]
#[ORM\Table(name: 'bulk_callback_log')]
#[ORM\HasLifecycleCallbacks]
#[Purgeable(field: 'createdAt', interval: '-3 months', bulk: true)]
class BulkCallbackLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column]
        private \DateTimeImmutable $createdAt
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\PostRemove]
    public function forgetTheFile(): void
    {
    }
}
