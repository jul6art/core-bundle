<?php

namespace Jul6Art\CoreBundle\Event\Interfaces;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Interface EventInterface.
 */
interface EventInterface
{
    public function getData(): ArrayCollection;

    /**
     * @return $this
     */
    public function setData(ArrayCollection $data): self;

    /**
     * @param $data
     *
     * @return $this
     */
    public function addData($data): self;

    /**
     * @param $data
     *
     * @return $this
     */
    public function removeData($data): self;
}
