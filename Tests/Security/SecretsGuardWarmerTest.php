<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Security;

use Jul6Art\CoreBundle\Security\SecretsGuard;
use Jul6Art\CoreBundle\Security\SecretsGuardWarmer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecretsGuardWarmer::class)]
final class SecretsGuardWarmerTest extends TestCase
{
    private const array VARIABLES = ['APP_SECRET', 'MERCURE_JWT_SECRET', 'JWT_PASSPHRASE', 'CORE_GUARD_TEST_KEY'];

    /** @var array<string, mixed> */
    private array $server = [];

    /** @var array<string, mixed> */
    private array $env = [];

    private string $dotenv;

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->server[$name] = $_SERVER[$name] ?? null;
            $this->env[$name] = $_ENV[$name] ?? null;
            unset($_SERVER[$name], $_ENV[$name]);
        }
        $this->dotenv = (string) tempnam(sys_get_temp_dir(), 'dotenv');
        file_put_contents($this->dotenv, "APP_SECRET=\nCORE_GUARD_TEST_KEY=committed-key\n");
    }

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
            if (null !== $this->server[$name]) {
                $_SERVER[$name] = $this->server[$name];
            }
            if (null !== $this->env[$name]) {
                $_ENV[$name] = $this->env[$name];
            }
        }
        @unlink($this->dotenv);
    }

    public function testAProductionCacheIsRefusedWhileASecretIsADefault(): void
    {
        $_SERVER['APP_SECRET'] = 'ChangeMe';
        $_SERVER['CORE_GUARD_TEST_KEY'] = 'committed-key';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('APP_SECRET, CORE_GUARD_TEST_KEY.');

        $this->warmer('prod')->warmUp(sys_get_temp_dir());
    }

    public function testTheMessageNamesTheVariablesNeverTheirValues(): void
    {
        $_SERVER['APP_SECRET'] = 'short-but-secret-value';
        $_SERVER['CORE_GUARD_TEST_KEY'] = 'committed-key';

        try {
            $this->warmer('prod')->warmUp(sys_get_temp_dir());
            self::fail('The production cache was built.');
        } catch (\RuntimeException $exception) {
            self::assertStringNotContainsString('short-but-secret-value', $exception->getMessage());
            self::assertStringNotContainsString('committed-key', $exception->getMessage());
        }
    }

    public function testAProductionCacheWithRealSecretsIsBuilt(): void
    {
        $_SERVER['APP_SECRET'] = '0123456789abcdef0123456789abcdef';
        $_ENV['CORE_GUARD_TEST_KEY'] = 'the-server-key';

        self::assertSame([], $this->warmer('prod')->warmUp(sys_get_temp_dir()));
    }

    public function testOutsideProductionTheWorkstationValuesAreFine(): void
    {
        $_SERVER['APP_SECRET'] = 'ChangeMe';

        self::assertSame([], $this->warmer('dev')->warmUp(sys_get_temp_dir()));
        self::assertSame([], $this->warmer('test')->warmUp(sys_get_temp_dir()));
    }

    public function testTheWarmerIsNeverSkipped(): void
    {
        self::assertFalse($this->warmer('prod')->isOptional(), 'cache:warmup skips an optional warmer unless asked to run it.');
    }

    private function warmer(string $environment): SecretsGuardWarmer
    {
        return new SecretsGuardWarmer(new SecretsGuard(['CORE_GUARD_TEST_KEY']), $environment, $this->dotenv);
    }
}
