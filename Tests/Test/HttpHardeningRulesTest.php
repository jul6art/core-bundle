<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Test;

use Jul6Art\CoreBundle\Test\HttpHardeningRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpHardeningRules::class)]
final class HttpHardeningRulesTest extends TestCase
{
    private const array FRAMEWORK = ['trusted_hosts' => '%env(TRUSTED_HOSTS)%', 'trusted_proxies' => '%env(TRUSTED_PROXIES)%'];
    private const array CORE = ['security_headers' => ['enabled' => true], 'secrets_guard' => ['enabled' => true]];

    public function testAHardenedConfigurationPasses(): void
    {
        self::assertSame([], HttpHardeningRules::violations(self::FRAMEWORK, self::CORE));
        self::assertSame([], HttpHardeningRules::violations(['trusted_hosts' => ['^example\.test$']], self::CORE), 'No proxy trusted at all is fine.');
    }

    /**
     * @param array<string, mixed> $framework
     * @param array<string, mixed> $core
     * @param non-empty-string     $named
     */
    #[DataProvider('weakConfigurations')]
    public function testEachWeaknessIsNamed(array $framework, array $core, string $named): void
    {
        $violations = HttpHardeningRules::violations($framework, $core);

        self::assertCount(1, $violations);
        self::assertStringStartsWith($named, $violations[0]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, non-empty-string}>
     */
    public static function weakConfigurations(): iterable
    {
        yield 'no trusted hosts' => [['trusted_proxies' => '127.0.0.1'], self::CORE, 'framework.trusted_hosts'];
        yield 'empty trusted hosts' => [['trusted_hosts' => []] + self::FRAMEWORK, self::CORE, 'framework.trusted_hosts'];
        yield 'REMOTE_ADDR in a string' => [['trusted_proxies' => '127.0.0.1, remote_addr'] + self::FRAMEWORK, self::CORE, 'framework.trusted_proxies'];
        yield 'REMOTE_ADDR in a list' => [['trusted_proxies' => ['REMOTE_ADDR']] + self::FRAMEWORK, self::CORE, 'framework.trusted_proxies'];
        yield 'headers off' => [self::FRAMEWORK, ['security_headers' => ['enabled' => false]] + self::CORE, 'core.security_headers'];
        yield 'guard unwritten' => [self::FRAMEWORK, ['security_headers' => ['enabled' => true]], 'core.secrets_guard'];
    }
}
