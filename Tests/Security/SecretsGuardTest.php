<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Security;

use Jul6Art\CoreBundle\Security\SecretsGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecretsGuard::class)]
final class SecretsGuardTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';

    public function testRealSecretsPass(): void
    {
        $guard = new SecretsGuard(['APP_ENCRYPTION_KEY'], jwt: true);

        self::assertSame([], $guard->violations([
            'APP_SECRET' => self::SECRET,
            'MERCURE_JWT_SECRET' => 'a-hub-key-of-the-server',
            'JWT_PASSPHRASE' => 'a-passphrase-of-the-server',
            'APP_ENCRYPTION_KEY' => 'a-key-of-the-server',
        ], [
            'APP_SECRET' => '',
            'MERCURE_JWT_SECRET' => '!ChangeThisMercureHubJWTSecretKey!',
            'JWT_PASSPHRASE' => '',
            'APP_ENCRYPTION_KEY' => '',
        ]));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function badAppSecrets(): iterable
    {
        yield 'undefined' => [null];
        yield 'empty' => [''];
        yield 'a placeholder' => ['ChangeMe'];
        yield 'the long placeholder' => ['change_me_to_a_random_32_char_string'];
        yield 'shorter than 32 characters' => ['0123456789abcdef0123456789abcde'];
        yield 'the committed value' => ['committed-0123456789abcdef0123456789'];
    }

    #[DataProvider('badAppSecrets')]
    public function testAnAppSecretThatIsADefaultIsRefused(?string $secret): void
    {
        self::assertSame(['APP_SECRET'], new SecretsGuard()->violations(['APP_SECRET' => $secret], ['APP_SECRET' => 'committed-0123456789abcdef0123456789']));
    }

    public function testTheMercurePlaceholderIsRefusedOnlyWhereTheVariableExists(): void
    {
        $guard = new SecretsGuard();

        self::assertSame(['MERCURE_JWT_SECRET'], $guard->violations(['APP_SECRET' => self::SECRET, 'MERCURE_JWT_SECRET' => '!ChangeThisMercureHubJWTSecretKey!'], []));
        self::assertSame([], $guard->violations(['APP_SECRET' => self::SECRET, 'MERCURE_JWT_SECRET' => null], []), 'An application without a hub has no hub key to change.');
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function badPassphrases(): iterable
    {
        yield 'undefined' => [null];
        yield 'empty' => [''];
        yield 'the placeholder' => ['change_me_jwt_passphrase'];
        yield 'the committed value' => ['the-workstation-passphrase'];
    }

    #[DataProvider('badPassphrases')]
    public function testAJwtPassphraseThatIsADefaultIsRefused(?string $passphrase): void
    {
        $environment = ['APP_SECRET' => self::SECRET, 'JWT_PASSPHRASE' => $passphrase];
        $committed = ['JWT_PASSPHRASE' => 'the-workstation-passphrase'];

        self::assertSame(['JWT_PASSPHRASE'], new SecretsGuard(jwt: true)->violations($environment, $committed));
        self::assertSame([], new SecretsGuard(jwt: false)->violations($environment, $committed), 'Without LexikJWTAuthenticationBundle, nothing reads it.');
    }

    public function testADeclaredSecretIsRefusedWhenEmptyOrCommitted(): void
    {
        $guard = new SecretsGuard(['STORAGE_SECRET', 'APP_ENCRYPTION_KEY', 'WTG_PREVIEW_SECRET']);

        self::assertSame(['STORAGE_SECRET', 'APP_ENCRYPTION_KEY', 'WTG_PREVIEW_SECRET'], $guard->violations([
            'APP_SECRET' => self::SECRET,
            'STORAGE_SECRET' => 'cereezercereezer',
            'APP_ENCRYPTION_KEY' => '',
            'WTG_PREVIEW_SECRET' => null,
        ], ['STORAGE_SECRET' => 'cereezercereezer']));
    }

    public function testEveryViolationIsNamedOnceWithoutItsValue(): void
    {
        $violations = new SecretsGuard(['APP_SECRET'], jwt: true)->violations(['APP_SECRET' => 'ChangeMe', 'MERCURE_JWT_SECRET' => '!ChangeThisMercureHubJWTSecretKey!', 'JWT_PASSPHRASE' => ''], []);

        self::assertSame(['APP_SECRET', 'MERCURE_JWT_SECRET', 'JWT_PASSPHRASE'], $violations);
    }

    public function testTheGuardReadsItsOwnVariablesAndTheDeclaredOnes(): void
    {
        self::assertSame(['APP_SECRET', 'MERCURE_JWT_SECRET', 'JWT_PASSPHRASE', 'STORAGE_SECRET'], new SecretsGuard(['STORAGE_SECRET', 'APP_SECRET'])->names());
    }

    public function testADotenvFileIsReadAsWritten(): void
    {
        $content = <<<'ENV'
            # A comment
            APP_SECRET=
            export JWT_PASSPHRASE=the-workstation-passphrase
            MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
            STORAGE_SECRET='single quoted' # after a quote
            STORAGE_KEY=cereezer # an inline comment
            ESCAPED="a \"quoted\" word"
            REFERENCE=${APP_SECRET}
            not a variable
            ENV;

        self::assertSame([
            'APP_SECRET' => '',
            'JWT_PASSPHRASE' => 'the-workstation-passphrase',
            'MERCURE_JWT_SECRET' => '!ChangeThisMercureHubJWTSecretKey!',
            'STORAGE_SECRET' => 'single quoted',
            'STORAGE_KEY' => 'cereezer',
            'ESCAPED' => 'a "quoted" word',
            'REFERENCE' => '${APP_SECRET}',
        ], SecretsGuard::parseDotenv($content));
    }
}
