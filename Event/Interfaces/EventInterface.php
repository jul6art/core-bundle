<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Event\Interfaces;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Interface EventInterface.
 */
interface EventInterface
{
    /**
     * @return ArrayCollection<int, mixed>
     */
    public function getData(): ArrayCollection;

    /**
     * @param ArrayCollection<int, mixed> $data
     */
    public function setData(ArrayCollection $data): static;

    public function addData(mixed $data): static;

    public function removeData(mixed $data): static;
}
