<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Manager;

use Jul6Art\CoreBundle\Manager\AbstractManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Jul6Art\CoreBundle\Tests\Fixtures\Repository\WidgetRepository;

/**
 * The "$widgetRepository" property name is what AbstractManager resolves from the
 * "WidgetManager" class name.
 *
 * @extends AbstractManager<Widget>
 */
class WidgetManager extends AbstractManager
{
    public function __construct(
        protected WidgetRepository $widgetRepository,
    ) {
    }
}
