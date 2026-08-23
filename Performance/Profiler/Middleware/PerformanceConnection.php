<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;

final class PerformanceConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly QueryTracker $tracker)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new PerformanceStatement(parent::prepare($sql), $this->tracker, $sql);
    }

    public function query(string $sql): Result
    {
        $handle = $this->tracker->start($sql);
        try {
            return parent::query($sql);
        } finally {
            $this->tracker->end($handle);
        }
    }

    public function exec(string $sql): int|string
    {
        $handle = $this->tracker->start($sql);
        try {
            return parent::exec($sql);
        } finally {
            $this->tracker->end($handle);
        }
    }
}
