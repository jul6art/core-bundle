<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler\Middleware;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;

final class PerformanceDriver extends AbstractDriverMiddleware
{
    public function __construct(DriverInterface $driver, private readonly QueryTracker $tracker)
    {
        parent::__construct($driver);
    }

    public function connect(
        #[\SensitiveParameter]
        array $params
    ): Connection {
        return new PerformanceConnection(parent::connect($params), $this->tracker);
    }
}
