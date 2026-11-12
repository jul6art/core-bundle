<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Test;

use Jul6Art\CoreBundle\Test\KernelConfiguration;
use Jul6Art\CoreBundle\Tests\Fixtures\ConfigurationKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KernelConfiguration::class)]
final class KernelConfigurationTest extends TestCase
{
    public function testItReadsWhatAnEnvironmentWritesWithoutBootingIt(): void
    {
        // The kernel's secret is too short for the guard its production enables: booting it would fail.
        $framework = KernelConfiguration::of(ConfigurationKernel::class, 'prod')->extension('framework');

        self::assertSame(['^example\.test$'], $framework['trusted_hosts'] ?? null);
        self::assertSame('%env(TRUSTED_PROXIES)%', $framework['trusted_proxies'] ?? null, 'Raw: an env var stays a placeholder, and the later file wins.');
        self::assertSame(['enabled' => true], KernelConfiguration::of(ConfigurationKernel::class, 'prod')->extension('core')['secrets_guard'] ?? null);
    }

    public function testEachEnvironmentReadsItsOwnFiles(): void
    {
        $framework = KernelConfiguration::of(ConfigurationKernel::class, 'dev')->extension('framework');

        self::assertArrayNotHasKey('trusted_hosts', $framework);
        self::assertSame('127.0.0.1,REMOTE_ADDR', $framework['trusted_proxies'] ?? null);
    }

    public function testItSaysWhichExtensionsTheBundlesRegister(): void
    {
        self::assertTrue(KernelConfiguration::of(ConfigurationKernel::class, 'prod')->has('core'));
        self::assertFalse(KernelConfiguration::of(ConfigurationKernel::class, 'prod')->has('security'), 'No SecurityBundle in this kernel.');
    }

    public function testAnExtensionNobodyConfiguresIsEmpty(): void
    {
        self::assertSame([], KernelConfiguration::of(ConfigurationKernel::class, 'prod')->extension('twig'));
    }
}
