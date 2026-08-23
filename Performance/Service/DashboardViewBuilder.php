<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Service;

use Jul6Art\CoreBundle\Performance\Store\PerformanceRecord;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;

final class DashboardViewBuilder
{
    private const MAX_ROWS = 5000;

    public function __construct(private readonly PerformanceStoreInterface $store)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $records = $this->store->list(self::MAX_ROWS, 0);

        $rows = array_map(static fn (PerformanceRecord $r): array => $r->toArray(), $records);
        $routeAggregates = $this->aggregateByRoute($records);

        return [
            'rows' => $rows,
            'route_aggregates' => $routeAggregates,
            'total_records' => $this->store->count(),
            'loaded_records' => \count($rows),
            'max_rows' => self::MAX_ROWS,
            'total_queries' => array_sum(array_column($rows, 'query_count')),
            'total_duration_ms' => array_sum(array_column($rows, 'duration_ms')),
            'filter_methods' => $this->distinctValues($rows, 'method'),
            'filter_status_codes' => $this->distinctValues($rows, 'status_code'),
        ];
    }

    /**
     * @param list<PerformanceRecord> $records
     *
     * @return list<array<string, mixed>>
     */
    private function aggregateByRoute(array $records): array
    {
        $aggregates = [];

        foreach ($records as $record) {
            $key = $record->route ?? '[unmatched]';
            $aggregates[$key] ??= $this->emptyAggregate($key);
            $this->accumulate($aggregates[$key], $record);
        }

        foreach ($aggregates as &$aggregate) {
            $this->finalize($aggregate);
        }
        unset($aggregate);

        usort($aggregates, static fn (array $a, array $b): int => $b['avg_query_count'] <=> $a['avg_query_count']);

        return $aggregates;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAggregate(string $route): array
    {
        return [
            'route' => $route,
            'hits' => 0,
            'avg_query_count' => 0.0,
            'max_query_count' => 0,
            'avg_duration_ms' => 0.0,
            'max_duration_ms' => 0.0,
            '_query_sum' => 0,
            '_duration_sum' => 0.0,
        ];
    }

    /**
     * @param array<string, mixed> $aggregate
     */
    private function accumulate(array &$aggregate, PerformanceRecord $record): void
    {
        ++$aggregate['hits'];
        $aggregate['_query_sum'] += $record->queryCount;
        $aggregate['_duration_sum'] += $record->durationMs;
        $aggregate['max_query_count'] = max($aggregate['max_query_count'], $record->queryCount);
        $aggregate['max_duration_ms'] = max($aggregate['max_duration_ms'], $record->durationMs);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<int|string>
     */
    private function distinctValues(array $rows, string $column): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if (null === $value || '' === $value) {
                continue;
            }
            $values[(string) $value] = $value;
        }

        ksort($values);

        return array_values($values);
    }

    /**
     * @param array<string, mixed> $aggregate
     */
    private function finalize(array &$aggregate): void
    {
        $hits = $aggregate['hits'];
        $aggregate['avg_query_count'] = $hits > 0 ? $aggregate['_query_sum'] / $hits : 0.0;
        $aggregate['avg_duration_ms'] = $hits > 0 ? $aggregate['_duration_sum'] / $hits : 0.0;
        unset($aggregate['_query_sum'], $aggregate['_duration_sum']);
    }
}
