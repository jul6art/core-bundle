<?php

namespace Jul6Art\CoreBundle\EventListener;

use Jul6Art\CoreBundle\Service\Traits\FlashBagAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\TranslatorAwareTrait;
use Jul6Art\CoreBundle\EventListener\Interfaces\EntityEventListenerInterface;

/**
 * Class AbstractEntityEventListener
 */
abstract class AbstractEntityEventListener extends AbstractEventListener implements EntityEventListenerInterface
{
    use FlashBagAwareTrait;
    use TranslatorAwareTrait;
}
