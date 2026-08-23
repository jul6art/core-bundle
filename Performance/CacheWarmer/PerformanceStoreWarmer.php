<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\CacheWarmer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class PerformanceStoreWarmer implements CacheWarmerInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($this->directory)) {
            $filesystem->mkdir($this->directory, 0o777);
        }

        @chmod($this->directory, 0o777);

        return [];
    }
}
