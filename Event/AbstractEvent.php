<?php

namespace Jul6Art\CoreBundle\Event;

use Jul6Art\CoreBundle\Event\Interfaces\EventInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class AbstractEvent
 */
abstract class AbstractEvent extends Event implements EventInterface
{
    public const CREATED = 'event.created';
    public const DELETED = 'event.deleted';
    public const EDITED = 'event.edited';
    public const VIEWED = 'event.viewed';

    /**
     * @var ArrayCollection
     */
    protected $data;

    /**
     * AbstractEvent constructor.
     */
    public function __construct()
    {
        $this->data = new ArrayCollection();
    }

    /**
     * @return ArrayCollection
     */
    public function getData(): ArrayCollection
    {
        return $this->data;
    }

    /**
     * @param ArrayCollection $data
     * @return $this
     */
    public function setData(ArrayCollection $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function addData($data): self
    {
        if (!$this->data->contains($data)) {
            $this->data->add($data);
        }

        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function removeData($data): self
    {
        if ($this->data->contains($data)) {
            $this->data->removeElement($data);
        }

        return $this;
    }
}
