<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler;

final class QueryHasher
{
    public function hash(string $sql): string
    {
        return sha1($this->normalize($sql));
    }

    public function normalize(string $sql): string
    {
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;
        $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;

        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "'?'", $sql) ?? $sql;
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '"?"', $sql) ?? $sql;

        $sql = preg_replace('/\b\d+\.?\d*\b/', '?', $sql) ?? $sql;

        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return trim($sql);
    }
}
