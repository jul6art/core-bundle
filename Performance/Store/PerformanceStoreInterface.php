<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Store;

interface PerformanceStoreInterface
{
    public function append(PerformanceRecord $record): void;

    /**
     * @return list<PerformanceRecord>
     */
    public function list(int $limit = 500, int $offset = 0): array;

    /**
     * @return iterable<PerformanceRecord>
     */
    public function listAll(): iterable;

    public function count(): int;

    public function clear(): void;
}
