<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Doctrine\Type;

use Jul6Art\CoreBundle\Security\Encryptor;

/**
 * Injects the {@see Encryptor} into {@see EncryptedStringType}, a DBAL type Doctrine
 * instantiates without going through the container.
 *
 * Wired on `kernel.request` and `console.command` at priority 4096 — both fire before any
 * Doctrine hydration or flush, so the type always holds its encryptor before a
 * confidential value is converted. Idempotent.
 *
 * The listener is registered from the bundle extension with **string** event names rather
 * than `#[AsEventListener(event: ConsoleEvents::COMMAND)]`: referencing that constant would
 * make the class unloadable in an application that has no `symfony/console`, which this
 * bundle does not require.
 */
final class EncryptedTypeRegistrar
{
    public function __construct(
        private readonly Encryptor $encryptor,
    ) {
    }

    public function register(): void
    {
        EncryptedStringType::setEncryptor($this->encryptor);
    }
}
