<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Security;

use Jul6Art\CoreBundle\Security\Encryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Encryptor::class)]
final class EncryptorTest extends TestCase
{
    public function testARoundTripReturnsThePlaintext(): void
    {
        $encryptor = self::encryptor();

        self::assertSame('LU12 3456 7890 1234', $encryptor->decrypt($encryptor->encrypt('LU12 3456 7890 1234')));
    }

    public function testAnEmptyStringSurvivesTheRoundTrip(): void
    {
        $encryptor = self::encryptor();

        self::assertSame('', $encryptor->decrypt($encryptor->encrypt('')));
    }

    public function testUtf8SurvivesTheRoundTrip(): void
    {
        $encryptor = self::encryptor();

        self::assertSame('Émilie Ünger — 中文', $encryptor->decrypt($encryptor->encrypt('Émilie Ünger — 中文')));
    }

    /**
     * A fresh nonce per call is what stops an observer from spotting that two rows hold
     * the same social-security number.
     */
    public function testTheSamePlaintextEncryptsToDifferentCiphertexts(): void
    {
        $encryptor = self::encryptor();

        self::assertNotSame($encryptor->encrypt('same'), $encryptor->encrypt('same'));
    }

    public function testTheCiphertextIsBase64AndNeverContainsThePlaintext(): void
    {
        $cipher = self::encryptor()->encrypt('needle');

        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/]+={0,2}$#', $cipher);
        self::assertStringNotContainsString('needle', $cipher);
    }

    public function testDecryptRejectsATamperedPayload(): void
    {
        $encryptor = self::encryptor();
        $raw = base64_decode($encryptor->encrypt('authentic'), true);
        self::assertIsString($raw);

        // Alter the very last byte — inside the Poly1305 tag — so the payload stays
        // well-formed but no longer authenticates. Swapping for a known different byte
        // rather than flipping a bit keeps the intent obvious and the types exact.
        $last = \strlen($raw) - 1;
        $raw[$last] = 'A' === $raw[$last] ? 'B' : 'A';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Unable to decrypt payload (wrong key or tampered data).');

        $encryptor->decrypt(base64_encode($raw));
    }

    public function testDecryptRejectsAPayloadFromAnotherKey(): void
    {
        $cipher = self::encryptor()->encrypt('secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Unable to decrypt payload (wrong key or tampered data).');

        self::encryptor(str_repeat('b', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES))->decrypt($cipher);
    }

    public function testDecryptRejectsNonBase64Input(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Malformed encrypted payload.');

        self::encryptor()->decrypt('not base64 !!');
    }

    public function testDecryptRejectsAPayloadTooShortToHoldANonceAndATag(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Malformed encrypted payload.');

        self::encryptor()->decrypt(base64_encode('short'));
    }

    public function testTheConstructorRejectsAKeyOfTheWrongLength(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('encryption key must be a base64-encoded %d-byte key', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

        new Encryptor(base64_encode('too-short'));
    }

    public function testTheConstructorRejectsANonBase64Key(): void
    {
        $this->expectException(\RuntimeException::class);

        new Encryptor('not base64 !!');
    }

    private static function encryptor(?string $rawKey = null): Encryptor
    {
        return new Encryptor(base64_encode($rawKey ?? str_repeat('a', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }
}
