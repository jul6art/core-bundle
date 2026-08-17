<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Factory;

use Jul6Art\CoreBundle\Factory\Interfaces\FactoryInterface;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;

class WidgetFactory implements FactoryInterface
{
    #[\Override]
    public static function create(mixed ...$args): Widget
    {
        $name = $args[0] ?? 'widget';

        return new Widget(\is_string($name) ? $name : 'widget');
    }
}
