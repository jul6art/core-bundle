<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\EntityListener;

use Jul6Art\CoreBundle\EntityListener\Interfaces\EntityListenerInterface;
use Jul6Art\CoreBundle\Service\Traits\FlashBagAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\TokenStorageAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\TranslatorAwareTrait;

/**
 * Class AbstractEntityListener.
 */
abstract class AbstractEntityListener implements EntityListenerInterface
{
    use FlashBagAwareTrait;
    use TokenStorageAwareTrait;
    use TranslatorAwareTrait;
}
