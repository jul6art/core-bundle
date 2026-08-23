<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;

final class PerformanceStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly QueryTracker $tracker,
        private readonly string $sql
    ) {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        $handle = $this->tracker->start($this->sql);
        try {
            return parent::execute();
        } finally {
            $this->tracker->end($handle);
        }
    }
}
