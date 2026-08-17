<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\CoreBundle\Repository\AbstractRepository;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;

/**
 * @extends AbstractRepository<Widget>
 */
class WidgetRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Widget::class);
    }
}
