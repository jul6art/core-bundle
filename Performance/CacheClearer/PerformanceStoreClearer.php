<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\CacheClearer;

use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

final class PerformanceStoreClearer implements CacheClearerInterface
{
    public function __construct(private readonly PerformanceStoreInterface $store)
    {
    }

    public function clear(string $cacheDir): void
    {
        $this->store->clear();
    }
}
