<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler;

final class QueryTracker
{
    /** @var array<int, array{sql: string, fingerprint: string, start: float}> */
    private array $inFlight = [];

    /** @var array<string, array{sql: string, count: int, total_time: float}> */
    private array $aggregates = [];

    private int $totalCount = 0;

    private float $totalTime = 0.0;

    private int $nextHandle = 0;

    public function __construct(private readonly QueryHasher $hasher)
    {
    }

    public function start(string $sql): int
    {
        $handle = $this->nextHandle++;
        $this->inFlight[$handle] = [
            'sql' => $sql,
            'fingerprint' => $this->hasher->hash($sql),
            'start' => microtime(true),
        ];

        return $handle;
    }

    public function end(int $handle): void
    {
        if (!isset($this->inFlight[$handle])) {
            return;
        }

        $entry = $this->inFlight[$handle];
        unset($this->inFlight[$handle]);

        $duration = microtime(true) - $entry['start'];
        $fingerprint = $entry['fingerprint'];

        if (!isset($this->aggregates[$fingerprint])) {
            $this->aggregates[$fingerprint] = [
                'sql' => $this->hasher->normalize($entry['sql']),
                'count' => 0,
                'total_time' => 0.0,
            ];
        }

        ++$this->aggregates[$fingerprint]['count'];
        $this->aggregates[$fingerprint]['total_time'] += $duration;

        ++$this->totalCount;
        $this->totalTime += $duration;
    }

    public function getQueryCount(): int
    {
        return $this->totalCount;
    }

    public function getDistinctCount(): int
    {
        return \count($this->aggregates);
    }

    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    /**
     * @return array<string, array{sql: string, count: int, total_time: float}>
     */
    public function getAggregates(): array
    {
        return $this->aggregates;
    }

    public function reset(): void
    {
        $this->inFlight = [];
        $this->aggregates = [];
        $this->totalCount = 0;
        $this->totalTime = 0.0;
        $this->nextHandle = 0;
    }
}
