<?php

namespace Jul6Art\CoreBundle\EventListener;

use Jul6Art\CoreBundle\EventListener\Interfaces\EventListenerInterface;
use Jul6Art\CoreBundle\Service\Traits\TokenStorageAwareTrait;

/**
 * Class AbstractEventListener.
 */
abstract class AbstractEventListener implements EventListenerInterface
{
    use TokenStorageAwareTrait;
}
