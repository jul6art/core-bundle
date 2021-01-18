<?php

namespace Jul6Art\CoreBundle\Factory\Interfaces;

/**
 * Interface FactoryInterface
 */
interface FactoryInterface
{
    /**
     * @param mixed ...$param
     * @return Object
     */
    public static function create(...$param): Object;
}
