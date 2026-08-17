<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Manager;

use Jul6Art\CoreBundle\Manager\AbstractManager;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;

/**
 * Declares "$mismatchedRepository" with a value that is not a repository, to prove
 * AbstractManager::getAbstractRepository() fails loudly instead of returning junk.
 *
 * @extends AbstractManager<Widget>
 */
class MismatchedManager extends AbstractManager
{
    protected string $mismatchedRepository = 'not-a-repository';
}
