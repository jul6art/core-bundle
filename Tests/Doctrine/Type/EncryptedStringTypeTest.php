<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;
use Jul6Art\CoreBundle\Security\Encryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptedStringType::class)]
final class EncryptedStringTypeTest extends TestCase
{
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = new SQLitePlatform();
        self::forgetEncryptor();
    }

    /**
     * Doctrine instantiates DBAL types itself, so the encryptor lives in a static
     * property. Tests must reset it or the "no encryptor" cases would pass or fail
     * depending on execution order.
     */
    protected function tearDown(): void
    {
        self::forgetEncryptor();

        parent::tearDown();
    }

    public function testTheColumnIsDeclaredAsAClob(): void
    {
        $type = new EncryptedStringType();

        self::assertSame(
            $this->platform->getClobTypeDeclarationSQL(['name' => 'iban']),
            $type->getSQLDeclaration(['name' => 'iban'], $this->platform)
        );
    }

    public function testTheValueIsEncryptedOnTheWayToTheDatabase(): void
    {
        EncryptedStringType::setEncryptor(self::encryptor());
        $type = new EncryptedStringType();

        $stored = $type->convertToDatabaseValue('LU12 3456', $this->platform);

        self::assertIsString($stored);
        self::assertStringNotContainsString('LU12 3456', $stored);
    }

    public function testTheValueIsDecryptedOnTheWayBack(): void
    {
        EncryptedStringType::setEncryptor(self::encryptor());
        $type = new EncryptedStringType();

        $stored = $type->convertToDatabaseValue('LU12 3456', $this->platform);

        self::assertSame('LU12 3456', $type->convertToPHPValue($stored, $this->platform));
    }

    /**
     * `null` and `''` are the two values that must never reach libsodium: they carry no
     * secret, and encrypting them would make an empty field indistinguishable from a
     * filled one for the ORM's change tracking.
     */
    public function testNullAndEmptyStringPassThroughUntouchedBothWays(): void
    {
        EncryptedStringType::setEncryptor(self::encryptor());
        $type = new EncryptedStringType();

        self::assertNull($type->convertToDatabaseValue(null, $this->platform));
        self::assertSame('', $type->convertToDatabaseValue('', $this->platform));
        self::assertNull($type->convertToPHPValue(null, $this->platform));
        self::assertSame('', $type->convertToPHPValue('', $this->platform));
    }

    /**
     * Failing loud is the whole safety property: a missing encryptor must never mean
     * "store the plaintext".
     */
    public function testConvertingWithoutAnEncryptorFailsLoudRatherThanStoringPlaintext(): void
    {
        $type = new EncryptedStringType();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('EncryptedStringType has no Encryptor');

        $type->convertToDatabaseValue('secret', $this->platform);
    }

    public function testReadingWithoutAnEncryptorAlsoFailsLoud(): void
    {
        $type = new EncryptedStringType();

        $this->expectException(\LogicException::class);

        $type->convertToPHPValue('whatever', $this->platform);
    }

    /**
     * The literal matters beyond PHP: applications write `encrypted_string` by hand in
     * `doctrine.dbal.types`, so renaming the constant's value breaks their mapping. Read
     * through reflection because a direct comparison is a tautology the analyser rejects.
     */
    public function testTheTypeNameIsPublished(): void
    {
        self::assertSame('encrypted_string', new \ReflectionClassConstant(EncryptedStringType::class, 'NAME')->getValue());
    }

    private static function forgetEncryptor(): void
    {
        new \ReflectionProperty(EncryptedStringType::class, 'encryptor')->setValue(null, null);
    }

    private static function encryptor(): Encryptor
    {
        return new Encryptor(base64_encode(str_repeat('a', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }
}
