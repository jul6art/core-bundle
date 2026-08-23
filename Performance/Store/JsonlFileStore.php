<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Store;

use Symfony\Component\Filesystem\Filesystem;

final class JsonlFileStore implements PerformanceStoreInterface
{
    private const ROTATION_DAILY = 'daily';
    private const ROTATION_WEEKLY = 'weekly';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $directory,
        private readonly string $rotation = self::ROTATION_DAILY,
        private readonly int $maxRecords = 100000
    ) {
        $this->filesystem = new Filesystem();
    }

    public function append(PerformanceRecord $record): void
    {
        $this->ensureDirectory();

        $file = $this->currentFile();
        $line = json_encode($record->toArray(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        if (false === $line) {
            return;
        }

        file_put_contents($file, $line."\n", \FILE_APPEND | \LOCK_EX);

        $this->enforceMaxRecords();
    }

    public function list(int $limit = 500, int $offset = 0): array
    {
        $records = [];
        $skipped = 0;

        foreach ($this->iterateLinesDesc() as $line) {
            if ($skipped < $offset) {
                ++$skipped;

                continue;
            }

            $decoded = json_decode($line, true);
            if (!\is_array($decoded)) {
                continue;
            }

            $records[] = PerformanceRecord::fromArray($decoded);

            if (\count($records) >= $limit) {
                break;
            }
        }

        return $records;
    }

    public function listAll(): iterable
    {
        foreach ($this->iterateLinesDesc() as $line) {
            $decoded = json_decode($line, true);
            if (!\is_array($decoded)) {
                continue;
            }

            yield PerformanceRecord::fromArray($decoded);
        }
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->storeFiles() as $file) {
            $count += $this->countLines($file);
        }

        return $count;
    }

    public function clear(): void
    {
        foreach ($this->storeFiles() as $file) {
            $this->filesystem->remove($file);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {
            $this->filesystem->mkdir($this->directory, 0o777);
        }
    }

    private function currentFile(): string
    {
        return $this->directory.\DIRECTORY_SEPARATOR.$this->currentFileName();
    }

    private function currentFileName(): string
    {
        return match ($this->rotation) {
            self::ROTATION_DAILY => 'performance-'.date('Y-m-d').'.jsonl',
            self::ROTATION_WEEKLY => 'performance-'.date('o-\WW').'.jsonl',
            default => 'performance.jsonl',
        };
    }

    /**
     * @return list<string>
     */
    private function storeFiles(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $pattern = $this->directory.\DIRECTORY_SEPARATOR.'performance*.jsonl';
        $files = glob($pattern) ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return \Generator<int, string>
     */
    private function iterateLinesDesc(): \Generator
    {
        $files = $this->storeFiles();
        $files = array_reverse($files);

        foreach ($files as $file) {
            foreach ($this->readLinesReverse($file) as $line) {
                if ('' === $line) {
                    continue;
                }

                yield $line;
            }
        }
    }

    /**
     * @return \Generator<int, string>
     */
    private function readLinesReverse(string $file): \Generator
    {
        $handle = @fopen($file, 'r');
        if (false === $handle) {
            return;
        }

        try {
            $lines = [];
            while (false !== ($line = fgets($handle))) {
                $lines[] = rtrim($line, "\r\n");
            }

            for ($i = \count($lines) - 1; $i >= 0; --$i) {
                yield $lines[$i];
            }
        } finally {
            fclose($handle);
        }
    }

    private function countLines(string $file): int
    {
        $handle = @fopen($file, 'r');
        if (false === $handle) {
            return 0;
        }

        try {
            $count = 0;
            while (false !== fgets($handle)) {
                ++$count;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    private function enforceMaxRecords(): void
    {
        if ($this->maxRecords <= 0) {
            return;
        }

        $files = $this->storeFiles();
        if ([] === $files) {
            return;
        }

        $total = $this->count();
        while ($total > $this->maxRecords && \count($files) > 1) {
            $oldest = array_shift($files);
            $this->filesystem->remove($oldest);
            $total = $this->count();
        }
    }
}
