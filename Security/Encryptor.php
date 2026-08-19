<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Security;

/**
 * Authenticated symmetric encryption for data-at-rest (libsodium XSalsa20-Poly1305
 * secretbox), meant for the few columns that must not be readable in a database dump —
 * social-security numbers, IBAN/BIC, and the like.
 *
 * The key is 32 raw bytes, supplied base64-encoded through `core.encryption_key` (point it
 * at an env var; never commit it). Each ciphertext embeds a fresh random nonce, so
 * encrypting the same plaintext twice yields different ciphertexts; decryption
 * authenticates the payload and throws on tampering.
 *
 * The service is only registered when `core.encryption_key` is configured, so an
 * application that does not encrypt anything carries no dead service and no missing-env
 * failure at boot.
 *
 * Requires `ext-sodium` (bundled with PHP since 7.2, but a distribution can omit it).
 */
final class Encryptor
{
    /** 32 raw key bytes. */
    private readonly string $key;

    public function __construct(
        #[\SensitiveParameter]
        string $base64Key,
    ) {
        $key = base64_decode($base64Key, true);

        if (false === $key || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($key)) {
            throw new \RuntimeException(\sprintf('The encryption key must be a base64-encoded %d-byte key.', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        $this->key = $key;
    }

    /** Returns base64(nonce ‖ ciphertext). */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce.$cipher);
    }

    /** Reverses {@see self::encrypt()}; throws if the payload is malformed or tampered. */
    public function decrypt(string $stored): string
    {
        $decoded = base64_decode($stored, true);

        if (false === $decoded || \strlen($decoded) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + \SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new \RuntimeException('Malformed encrypted payload.');
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);

        if (false === $plain) {
            throw new \RuntimeException('Unable to decrypt payload (wrong key or tampered data).');
        }

        return $plain;
    }
}
