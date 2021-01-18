<?php

namespace Jul6Art\CoreBundle\Event\Interfaces;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Interface EventInterface
 */
interface EventInterface
{
    /**
     * @return ArrayCollection
     */
    public function getData(): ArrayCollection;

    /**
     * @param ArrayCollection $data
     * @return $this
     */
    public function setData(ArrayCollection $data): self;

    /**
     * @param $data
     * @return $this
     */
    public function addData($data): self;

    /**
     * @param $data
     * @return $this
     */
    public function removeData($data): self;
}
