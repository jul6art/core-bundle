<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Factory\Interfaces;

/**
 * Interface FactoryInterface.
 */
interface FactoryInterface
{
    public static function create(mixed ...$args): object;
}
