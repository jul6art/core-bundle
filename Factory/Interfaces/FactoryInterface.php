<?php

namespace Jul6Art\CoreBundle\Factory\Interfaces;

/**
 * Interface FactoryInterface
 */
interface FactoryInterface
{
    /**
     * @param mixed ...$args
     * @return Object
     */
    public static function create(...$args): Object;
}
