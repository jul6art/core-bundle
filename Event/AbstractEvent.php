<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Event;

use Doctrine\Common\Collections\ArrayCollection;
use Jul6Art\CoreBundle\Event\Interfaces\EventInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class AbstractEvent.
 */
abstract class AbstractEvent extends Event implements EventInterface
{
    public const string CREATED = 'event.created';
    public const string DELETED = 'event.deleted';
    public const string EDITED = 'event.edited';
    public const string VIEWED = 'event.viewed';

    /**
     * @var ArrayCollection<int, mixed>
     */
    protected ArrayCollection $data;

    public function __construct()
    {
        $this->data = new ArrayCollection();
    }

    #[\Override]
    public function getData(): ArrayCollection
    {
        return $this->data;
    }

    #[\Override]
    public function setData(ArrayCollection $data): static
    {
        $this->data = $data;

        return $this;
    }

    #[\Override]
    public function addData(mixed $data): static
    {
        if (!$this->data->contains($data)) {
            $this->data->add($data);
        }

        return $this;
    }

    #[\Override]
    public function removeData(mixed $data): static
    {
        if ($this->data->contains($data)) {
            $this->data->removeElement($data);
        }

        return $this;
    }
}
