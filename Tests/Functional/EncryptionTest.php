<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedTypeRegistrar;
use Jul6Art\CoreBundle\Security\Encryptor;
use Jul6Art\CoreBundle\Service\CascadeSoftDeleteHelper;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Gadget;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Wiring of the opt-in bricks: what the container holds with and without
 * `core.encryption_key`, and whether the DBAL type really encrypts on the way to a real
 * database.
 */
#[CoversNothing]
final class EncryptionTest extends AbstractFunctionalTestCase
{
    /** base64 of 32 × "a" — a 32-byte key, as SODIUM_CRYPTO_SECRETBOX_KEYBYTES requires. */
    private const string KEY_CONFIG = 'YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=';

    #[\Override]
    protected function tearDown(): void
    {
        new \ReflectionProperty(EncryptedStringType::class, 'encryptor')->setValue(null, null);

        parent::tearDown();
    }

    /**
     * Without a key there must be nothing to register: an application that encrypts
     * nothing should not boot into a missing-env failure just because the bundle is
     * installed.
     */
    public function testWithoutAKeyNeitherTheEncryptorNorItsListenerExists(): void
    {
        $container = $this->boot();

        self::assertFalse($container->has(Encryptor::class));
        self::assertFalse($container->has(EncryptedTypeRegistrar::class));
    }

    public function testAnEmptyKeyIsTreatedAsNoKey(): void
    {
        $container = $this->boot('test', ['encryption_key' => '']);

        self::assertFalse($container->has(Encryptor::class));
    }

    public function testWithAKeyBothServicesAreRegisteredAndTheEncryptorWorks(): void
    {
        $container = $this->boot('test', ['encryption_key' => self::KEY_CONFIG]);

        self::assertTrue($container->has(EncryptedTypeRegistrar::class));

        $encryptor = $container->get(Encryptor::class);
        self::assertInstanceOf(Encryptor::class, $encryptor);
        self::assertSame('secret', $encryptor->decrypt($encryptor->encrypt('secret')));
    }

    /**
     * The listener is the only thing standing between a confidential column and a
     * `LogicException` at hydration time, so its wiring is asserted explicitly rather
     * than assumed.
     */
    public function testTheListenerIsWiredOnBothTheHttpAndTheConsoleEntryPoints(): void
    {
        $container = $this->boot('test', ['encryption_key' => self::KEY_CONFIG]);

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        self::assertTrue(self::hasRegistrarListener($dispatcher, 'kernel.request'), 'The registrar must run on every HTTP request.');
        self::assertTrue(self::hasRegistrarListener($dispatcher, 'console.command'), 'The registrar must run on every console command.');
    }

    private static function hasRegistrarListener(EventDispatcherInterface $dispatcher, string $event): bool
    {
        return array_any($dispatcher->getListeners($event), static fn (callable|array $listener): bool => \is_array($listener) && $listener[0] instanceof EncryptedTypeRegistrar);
    }

    /**
     * End-to-end: the ORM sees plaintext, the database holds ciphertext. Asserted through
     * raw SQL because the ORM would otherwise decrypt what we are trying to inspect.
     */
    public function testTheColumnIsCipheredInTheDatabaseAndPlainInThePhpObject(): void
    {
        $container = $this->boot('test', ['encryption_key' => self::KEY_CONFIG], withOrm: true);

        $registrar = $container->get(EncryptedTypeRegistrar::class);
        self::assertInstanceOf(EncryptedTypeRegistrar::class, $registrar);
        $registrar->register();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Gadget::class)]);

        $gadget = new Gadget()->setSecret('LU12 3456 7890');
        $entityManager->persist($gadget);
        $entityManager->flush();
        $entityManager->clear();

        $stored = $entityManager->getConnection()->executeQuery('SELECT secret FROM gadget')->fetchOne();
        self::assertIsString($stored);
        self::assertStringNotContainsString('LU12', $stored);

        $reloaded = $entityManager->getRepository(Gadget::class)->find($gadget->getId());
        self::assertInstanceOf(Gadget::class, $reloaded);
        self::assertSame('LU12 3456 7890', $reloaded->getSecret());
    }

    public function testAnEmptySecretIsNotEncrypted(): void
    {
        $container = $this->boot('test', ['encryption_key' => self::KEY_CONFIG], withOrm: true);

        $registrar = $container->get(EncryptedTypeRegistrar::class);
        self::assertInstanceOf(EncryptedTypeRegistrar::class, $registrar);
        $registrar->register();

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Gadget::class)]);

        $entityManager->persist(new Gadget());
        $entityManager->flush();

        self::assertNull($entityManager->getConnection()->executeQuery('SELECT secret FROM gadget')->fetchOne());
    }

    /**
     * The helper needs `doctrine.orm.entity_manager`; registering it unconditionally would
     * break the container of every application that installs the bundle without the ORM.
     */
    public function testTheCascadeHelperFollowsTheDoctrineBundle(): void
    {
        self::assertFalse($this->boot()->has(CascadeSoftDeleteHelper::class));
        self::assertTrue($this->boot('test', [], withOrm: true)->has(CascadeSoftDeleteHelper::class));
    }
}
