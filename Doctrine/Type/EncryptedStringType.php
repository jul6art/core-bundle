<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Jul6Art\CoreBundle\Security\Encryptor;

/**
 * Transparent at-rest encryption for a string column. The ORM only ever sees the
 * plaintext — change tracking, validation and forms keep working normally — while the
 * ciphertext exists only in the database (`TEXT`).
 *
 * The {@see Encryptor} cannot be injected through the constructor because Doctrine
 * instantiates DBAL types itself, so it is set once at boot by
 * {@see EncryptedTypeRegistrar}. If a non-empty value is converted before that happened,
 * we fail loud — never silently storing plaintext.
 *
 * Register the type from the application:
 *
 * ```yaml
 * doctrine:
 *     dbal:
 *         types:
 *             encrypted_string: Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType
 * ```
 */
final class EncryptedStringType extends Type
{
    public const string NAME = 'encrypted_string';

    private static ?Encryptor $encryptor = null;

    public static function setEncryptor(Encryptor $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null === $value ? null : '';
        }

        return self::encryptor()->encrypt(self::asString($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null === $value ? null : '';
        }

        return self::encryptor()->decrypt(self::asString($value));
    }

    /**
     * The column is a string column, so anything that is neither a string nor Stringable is
     * a mapping mistake. Failing here names the offending type instead of silently storing
     * the result of a lossy cast.
     */
    private static function asString(mixed $value): string
    {
        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new \LogicException(\sprintf('%s can only convert strings, got %s.', self::NAME, get_debug_type($value)));
        }

        return (string) $value;
    }

    private static function encryptor(): Encryptor
    {
        if (null === self::$encryptor) {
            throw new \LogicException('EncryptedStringType has no Encryptor — it must be set at boot by EncryptedTypeRegistrar.');
        }

        return self::$encryptor;
    }
}
