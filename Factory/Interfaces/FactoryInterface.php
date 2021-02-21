<?php

namespace Jul6Art\CoreBundle\Factory\Interfaces;

/**
 * Interface FactoryInterface.
 */
interface FactoryInterface
{
    /**
     * @param mixed ...$args
     */
    public static function create(...$args): object;
}
