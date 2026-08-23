<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;

final class PerformanceMiddleware implements Middleware
{
    public function __construct(private readonly QueryTracker $tracker)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new PerformanceDriver($driver, $this->tracker);
    }
}
