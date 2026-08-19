<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Chronicle;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\ForgetfulNote;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Note;
use Jul6Art\CoreBundle\Util\Strings;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The entity traits against a real in-memory SQLite database, because what matters about them is
 * not the getters — it is what Doctrine does with the mapping and the lifecycle callbacks.
 */
#[CoversNothing]
final class EntityTraitsTest extends AbstractFunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->boot('test', [], withOrm: true);

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(Chronicle::class),
            $this->entityManager->getClassMetadata(Note::class),
            $this->entityManager->getClassMetadata(ForgetfulNote::class),
        ]);
    }

    // ── timestamps ────────────────────────────────────────────────────────

    public function testCreatedAtIsStampedAtInsertAndUpdatedAtStaysNull(): void
    {
        $chronicle = $this->persist(new Chronicle());

        self::assertNotNull($chronicle->getCreatedAt());
        self::assertNull($chronicle->getUpdatedAt(), 'Une ligne jamais modifiée ne doit pas prétendre l\'avoir été.');
    }

    public function testUpdatedAtIsStampedOnTheFirstUpdateOnly(): void
    {
        $chronicle = $this->persist(new Chronicle());
        $createdAt = $chronicle->getCreatedAt();

        $chronicle->setEmail('grace@example.test');
        $this->entityManager->flush();

        $updatedAt = $chronicle->getUpdatedAt();
        self::assertNotNull($updatedAt, 'PreUpdate doit avoir posé updatedAt.');
        self::assertSame(
            $createdAt->format('Y-m-d H:i:s'),
            $chronicle->getCreatedAt()->format('Y-m-d H:i:s'),
            'createdAt est mappé updatable: false — un UPDATE ne doit pas y toucher.',
        );
    }

    /**
     * `updatable: false` sur `createdAt` : même réécrit en mémoire, il ne part pas dans l'UPDATE.
     * C'est ce qui protège l'historique d'un `setCreatedAt()` qu'on serait tenté d'ajouter.
     */
    public function testCreatedAtIsNeverPartOfAnUpdate(): void
    {
        $chronicle = $this->persist(new Chronicle());
        $id = $chronicle->getId();
        $original = $chronicle->getCreatedAt()->format('Y-m-d H:i:s');

        new \ReflectionProperty(Chronicle::class, 'createdAt')
            ->setValue($chronicle, new \DateTimeImmutable('2000-01-01 00:00:00'));

        $chronicle->setEmail('rewritten@example.test');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(Chronicle::class, $id);
        self::assertInstanceOf(Chronicle::class, $reloaded);
        self::assertSame($original, $reloaded->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testTheLightweightTraitCarriesNoUpdatedAtColumn(): void
    {
        $note = $this->persist(new Note());

        self::assertNotNull($note->getCreatedAt());
        self::assertFalse(
            $this->entityManager->getClassMetadata(Note::class)->hasField('updatedAt'),
            'CreatedAtTrait ne doit pas introduire updated_at — c\'est toute la raison de son existence.',
        );
    }

    /**
     * Le piège n°1 de ces traits, exercé et pas seulement documenté : sans
     * `#[ORM\HasLifecycleCallbacks]`, Doctrine ignore `#[ORM\PrePersist]` **en silence**. La
     * propriété reste non initialisée, et c'est l'INSERT qui casse — loin de la cause.
     */
    public function testWithoutHasLifecycleCallbacksTheStampNeverHappens(): void
    {
        $metadata = $this->entityManager->getClassMetadata(ForgetfulNote::class);
        self::assertSame([], $metadata->lifecycleCallbacks, 'Aucun callback ne doit être enregistré.');

        $this->entityManager->persist(new ForgetfulNote());

        // Doctrine lit `null` sur la propriété non initialisée : c'est la base qui refuse, à
        // l'INSERT, avec un message qui ne mentionne ni le trait ni l'attribute manquante.
        $this->expectException(NotNullConstraintViolationException::class);
        $this->entityManager->flush();
    }

    // ── soft delete ───────────────────────────────────────────────────────

    public function testSoftDeleteMarksTheRowAndRestoreClearsIt(): void
    {
        $chronicle = $this->persist(new Chronicle());
        self::assertFalse($chronicle->isDeleted());

        $chronicle->softDelete();
        $this->entityManager->flush();

        self::assertTrue($chronicle->isDeleted());
        self::assertNotNull($chronicle->getDeletedAt());

        $chronicle->restore();
        $this->entityManager->flush();

        self::assertFalse($chronicle->isDeleted());
        self::assertNull($chronicle->getDeletedAt());
    }

    /**
     * Le cas qui justifie le suffixe : une colonne UNIQUE. Sans marquage, recréer une ligne avec
     * la même valeur qu'une ligne supprimée en douceur échoue à l'INSERT.
     */
    public function testTheDeletedSuffixFreesAUniqueValue(): void
    {
        $first = $this->persist(new Chronicle('ada@example.test'));

        $first->setEmail(Strings::markDeleted($first->getEmail()));
        $first->softDelete();
        $this->entityManager->flush();

        $second = $this->persist(new Chronicle('ada@example.test'));

        self::assertNotSame($first->getId(), $second->getId());
        self::assertStringStartsWith('ada@example.test'.Strings::DELETED_SUFFIX, $first->getEmail());
        self::assertSame('ada@example.test', Strings::restoreDeleted($first->getEmail()));
    }

    public function testMarkingAnAlreadyMarkedValueIsIdempotent(): void
    {
        $once = Strings::markDeleted('ada@example.test');

        self::assertSame($once, Strings::markDeleted($once));
    }

    public function testRestoringLeavesUnsuffixedValuesAndNullAlone(): void
    {
        self::assertSame('ada@example.test', Strings::restoreDeleted('ada@example.test'));
        self::assertNull(Strings::restoreDeleted(null));
    }

    /**
     * Le suffixe n'est retiré qu'en **fin** de valeur et seulement suivi de chiffres : une adresse
     * qui contiendrait le marqueur au milieu n'est pas tronquée.
     */
    public function testRestoringOnlyStripsATrailingMarker(): void
    {
        $value = 'ada'.Strings::DELETED_SUFFIX.'42@example.test';

        self::assertSame($value, Strings::restoreDeleted($value));
    }

    // ── auditable ─────────────────────────────────────────────────────────

    public function testTheActorColumnsAreWrittenExplicitly(): void
    {
        $chronicle = new Chronicle();

        // Aucun callback ici, volontairement : deviner l'utilisateur courant demanderait le
        // contexte de sécurité dans une entité.
        self::assertNull($chronicle->getCreatedBy());

        $chronicle->setCreatedBy('ada@example.test')->setUpdatedBy('grace@example.test');
        $id = $this->persist($chronicle)->getId();

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Chronicle::class, $id);

        self::assertInstanceOf(Chronicle::class, $reloaded);
        self::assertSame('ada@example.test', $reloaded->getCreatedBy());
        self::assertSame('grace@example.test', $reloaded->getUpdatedBy());
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }
}
