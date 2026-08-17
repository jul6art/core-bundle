<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Manager;

use Jul6Art\CoreBundle\Manager\AbstractManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;

/**
 * Declares no repository property at all, so the reflection lookup must fail.
 *
 * @extends AbstractManager<Widget>
 */
class OrphanManager extends AbstractManager
{
}
