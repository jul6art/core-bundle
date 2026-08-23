<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Service;

use Jul6Art\CoreBundle\Performance\Store\PerformanceRecord;

final class PerformanceExporter
{
    public const CSV_COLUMNS = [
        'id',
        'timestamp',
        'route',
        'method',
        'uri',
        'status_code',
        'duration_ms',
        'memory_peak_bytes',
        'query_count',
        'distinct_query_count',
        'db_time_ms',
        'response_size_bytes',
    ];

    /**
     * @param iterable<PerformanceRecord> $records
     */
    public function stream(iterable $records, string $format): void
    {
        match ($format) {
            'csv' => $this->streamCsv($records),
            'json' => $this->streamJson($records),
            default => null,
        };
    }

    /**
     * @param iterable<PerformanceRecord> $records
     */
    private function streamCsv(iterable $records): void
    {
        $handle = fopen('php://output', 'w');
        if (false === $handle) {
            return;
        }

        try {
            fputcsv($handle, self::CSV_COLUMNS);
            foreach ($records as $record) {
                fputcsv($handle, $this->toRow($record));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param iterable<PerformanceRecord> $records
     */
    private function streamJson(iterable $records): void
    {
        echo '[';
        $first = true;
        foreach ($records as $record) {
            $encoded = json_encode($record->toArray(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            if (false === $encoded) {
                continue;
            }
            echo $first ? $encoded : ','.$encoded;
            $first = false;
        }
        echo ']';
    }

    /**
     * @return list<string|int|float|null>
     */
    private function toRow(PerformanceRecord $record): array
    {
        return [
            $record->id,
            $record->timestamp,
            $record->route,
            $record->method,
            $record->uri,
            $record->statusCode,
            $record->durationMs,
            $record->memoryPeakBytes,
            $record->queryCount,
            $record->distinctQueryCount,
            $record->dbTimeMs,
            $record->responseSizeBytes,
        ];
    }
}
