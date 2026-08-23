<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Store;

final class PerformanceRecord
{
    /**
     * @param list<array{sql: string, count: int, total_time: float}> $queries
     */
    public function __construct(
        public readonly string $id,
        public readonly float $timestamp,
        public readonly ?string $route,
        public readonly string $method,
        public readonly string $uri,
        public readonly int $statusCode,
        public readonly float $durationMs,
        public readonly int $memoryPeakBytes,
        public readonly int $queryCount,
        public readonly int $distinctQueryCount,
        public readonly float $dbTimeMs,
        public readonly int $responseSizeBytes,
        public readonly array $queries = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp,
            'route' => $this->route,
            'method' => $this->method,
            'uri' => $this->uri,
            'status_code' => $this->statusCode,
            'duration_ms' => $this->durationMs,
            'memory_peak_bytes' => $this->memoryPeakBytes,
            'query_count' => $this->queryCount,
            'distinct_query_count' => $this->distinctQueryCount,
            'db_time_ms' => $this->dbTimeMs,
            'response_size_bytes' => $this->responseSizeBytes,
            'queries' => $this->queries,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array{sql: string, count: int, total_time: float}> $queries */
        $queries = \is_array($data['queries'] ?? null) ? $data['queries'] : [];

        return new self(
            id: (string) $data['id'],
            timestamp: (float) $data['timestamp'],
            route: isset($data['route']) ? (string) $data['route'] : null,
            method: (string) $data['method'],
            uri: (string) $data['uri'],
            statusCode: (int) $data['status_code'],
            durationMs: (float) $data['duration_ms'],
            memoryPeakBytes: (int) $data['memory_peak_bytes'],
            queryCount: (int) $data['query_count'],
            distinctQueryCount: (int) $data['distinct_query_count'],
            dbTimeMs: (float) $data['db_time_ms'],
            responseSizeBytes: (int) $data['response_size_bytes'],
            queries: $queries,
        );
    }
}
