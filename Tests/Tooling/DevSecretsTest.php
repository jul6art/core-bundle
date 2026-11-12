<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Tooling;

use Jul6Art\CoreBundle\Security\SecretsGuard;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * `bin/dev-secrets` (ADR-0002): the secrets a `prod` cache needs on a workstation, judged as the secrets guard
 * judges them. A workstation value the guard accepts is taken over — the local stack goes with it —, one it
 * refuses is replaced by a fresh one, and nothing lands where the development stack reads it.
 */
#[CoversNothing]
final class DevSecretsTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/dev-secrets-'.bin2hex(random_bytes(6));
        mkdir($this->project.'/config/packages', 0o700, true);
        $this->write('.env', "APP_SECRET=\nMERCURE_JWT_SECRET=\nJWT_PASSPHRASE=\nAPP_ENCRYPTION_KEY=\nSTORAGE_SECRET=committed-storage-secret-0123456789\n");
        $this->write('config/packages/core.yaml', "core:\n    secrets_guard:\n        enabled: true\n        variables: [APP_ENCRYPTION_KEY, STORAGE_SECRET]\n");
        $this->write('config/packages/lexik_jwt_authentication.yaml', "lexik_jwt_authentication:\n    pass_phrase: '%env(JWT_PASSPHRASE)%'\n");
    }

    protected function tearDown(): void
    {
        $this->remove($this->project);
    }

    public function testAWorkstationValueTheGuardAcceptsIsTakenOverIntoTheProductionFileOnly(): void
    {
        $this->write('.env.dev', "APP_ENCRYPTION_KEY=development-key-of-the-local-stack-0123\nJWT_PASSPHRASE=the-local-pairs-passphrase-0123456789\n");
        $this->write('.env.local', "MERCURE_JWT_SECRET='the-local-hubs-secret-0123456789abcdef'\nDEFAULT_URI=https://app.localhost\n");
        $local = $this->read('.env.local');

        $output = $this->secrets();

        $written = SecretsGuard::parseDotenv($this->read('.env.prod.local'));
        self::assertSame('development-key-of-the-local-stack-0123', $written['APP_ENCRYPTION_KEY'] ?? null);
        self::assertSame('the-local-pairs-passphrase-0123456789', $written['JWT_PASSPHRASE'] ?? null, 'The local key pair goes with it.');
        self::assertSame('the-local-hubs-secret-0123456789abcdef', $written['MERCURE_JWT_SECRET'] ?? null, 'Read where the development stack reads it, .env.local included.');
        self::assertSame($local, $this->read('.env.local'), 'What the development stack reads is never touched.');
        self::assertSame('0600', substr(\sprintf('%o', fileperms($this->project.'/.env.prod.local')), -4));
        self::assertStringNotContainsString('regenerate', $output);
        $this->assertTheGuardBuildsTheCache();
    }

    public function testAValueTheGuardRefusesIsReplacedByAFreshOneAndSaid(): void
    {
        // The recipes' placeholders in the files the development stack reads, and the value .env commits.
        $this->write('.env.docker', "MERCURE_JWT_SECRET='!ChangeThisMercureHubJWTSecretKey!'\nJWT_PASSPHRASE=change_me_jwt_passphrase\nSTORAGE_SECRET=committed-storage-secret-0123456789\n");
        $this->write('.env.dev', "APP_ENCRYPTION_KEY=development-key-of-the-local-stack-0123\n");

        $output = $this->secrets();

        $written = SecretsGuard::parseDotenv($this->read('.env.prod.local'));
        self::assertNotSame('!ChangeThisMercureHubJWTSecretKey!', $written['MERCURE_JWT_SECRET'] ?? null);
        self::assertNotSame('change_me_jwt_passphrase', $written['JWT_PASSPHRASE'] ?? null);
        self::assertNotSame('committed-storage-secret-0123456789', $written['STORAGE_SECRET'] ?? null);
        foreach (['MERCURE_JWT_SECRET', 'JWT_PASSPHRASE', 'STORAGE_SECRET'] as $name) {
            self::assertStringContainsString($name.': the workstation\'s value is one the secrets guard refuses', $output);
        }
        self::assertStringContainsString('signs no token', $output, 'The local pair stays the development one: never regenerated for a refused passphrase.');
        self::assertStringNotContainsString('CONSOLE', $output);
        $this->assertTheGuardBuildsTheCache();
    }

    public function testWithoutAnyWorkstationValueEverySecretIsFreshAndThePairRegenerated(): void
    {
        $output = $this->secrets();

        $written = SecretsGuard::parseDotenv($this->read('.env.prod.local'));
        self::assertGreaterThanOrEqual(SecretsGuard::APP_SECRET_MIN_LENGTH, \strlen($written['APP_SECRET'] ?? ''));
        self::assertStringContainsString('CONSOLE lexik:jwt:generate-keypair --overwrite --no-interaction', $output, 'A fresh passphrase opens no existing pair: a new one.');
        $this->assertTheGuardBuildsTheCache();
    }

    public function testAValueIsNeverReplacedNorShown(): void
    {
        $this->secrets();
        $first = $this->read('.env.prod.local');

        $output = $this->secrets();

        self::assertSame($first, $this->read('.env.prod.local'));
        self::assertStringContainsString('nothing to write', $output);
        foreach (SecretsGuard::parseDotenv($first) as $value) {
            self::assertStringNotContainsString($value, $output);
        }
    }

    public function testAnotherFileWhenTheStackReadsIt(): void
    {
        $this->write('.env.docker', "DATABASE_URL=mysql://app@db/app\n");

        $this->secrets('.env.docker');

        self::assertArrayHasKey('APP_SECRET', SecretsGuard::parseDotenv($this->read('.env.docker')));
        self::assertFileDoesNotExist($this->project.'/.env.prod.local');
    }

    public function testOutsideAProjectNothingIsWritten(): void
    {
        unlink($this->project.'/.env');

        [$exit, $output] = $this->execute([]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('run it from the project\'s root', $output);
        self::assertFileDoesNotExist($this->project.'/.env.prod.local');
    }

    /** The script judges as the guard does: its placeholders are the guard's, word for word. */
    public function testTheScriptsPlaceholdersAreTheGuards(): void
    {
        $script = (string) file_get_contents(\dirname(__DIR__, 2).'/bin/dev-secrets');
        foreach (SecretsGuard::PLACEHOLDERS as $name => $placeholders) {
            self::assertMatchesRegularExpression('/^\s+'.$name.'\) printf \'%s\\\\n\' '.preg_quote(implode(' ', array_map(static fn (string $p): string => "'".$p."'", $placeholders)), '/').' ;;$/m', $script, $name);
        }
        self::assertSame(\count(SecretsGuard::PLACEHOLDERS), preg_match_all('/^\s+[A-Z_]+\) printf /m', $script));
    }

    /**
     * What a `prod` kernel of this project reads — `.env`, then `.env.local`, then `.env.prod.local` — passes the
     * guard: the cache warms.
     */
    private function assertTheGuardBuildsTheCache(): void
    {
        $committed = SecretsGuard::parseDotenv($this->read('.env'));
        $environment = $committed;
        foreach (['.env.local', '.env.prod.local'] as $file) {
            if (is_file($this->project.'/'.$file)) {
                $environment = array_merge($environment, SecretsGuard::parseDotenv($this->read($file)));
            }
        }
        $guard = new SecretsGuard(['APP_ENCRYPTION_KEY', 'STORAGE_SECRET'], true);
        $values = [];
        foreach ($guard->names() as $name) {
            $values[$name] = $environment[$name] ?? null;
        }

        self::assertSame([], $guard->violations($values, $committed));
    }

    private function secrets(?string $file = null): string
    {
        [$exit, $output] = $this->execute(null === $file ? [] : [$file]);
        self::assertSame(0, $exit, $output);

        return $output;
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string}
     */
    private function execute(array $arguments): array
    {
        $command = array_merge([\dirname(__DIR__, 2).'/bin/dev-secrets'], $arguments);
        // The console that would regenerate the key pair: it only says what it was asked.
        $environment = ['PATH' => (string) getenv('PATH'), 'HOME' => $this->project, 'CONSOLE' => 'echo CONSOLE'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->project, $environment);
        self::assertIsResource($process);
        // A few lines at most on each: read one after the other, nothing fills a pipe.
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    private function write(string $file, string $content): void
    {
        file_put_contents($this->project.'/'.$file, $content);
    }

    private function read(string $file): string
    {
        return (string) file_get_contents($this->project.'/'.$file);
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ('.' !== $entry && '..' !== $entry) {
                    $this->remove($path.'/'.$entry);
                }
            }
            rmdir($path);

            return;
        }
        if (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}
