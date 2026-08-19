<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\CoreBundle\Command\PurgeCommand;
use Jul6Art\CoreBundle\Event\EntityPurgedEvent;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\ConditionalPurgeableLog;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\PurgeableLog;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\RepeatablePurgeableLog;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * The purge against a real database. Row counts are read through raw SQL so the ORM identity
 * map cannot mask a missing — or an unwanted — delete.
 */
#[CoversNothing]
final class PurgeCommandTest extends AbstractFunctionalTestCase
{
    private ContainerInterface $container;
    private EntityManagerInterface $entityManager;

    /** @var list<EntityPurgedEvent> */
    private array $events = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->events = [];
        $this->container = $this->boot('test', ['purge' => ['batch_size' => 3, 'aliases' => ['legacy:purge']]], withOrm: true);

        $entityManager = $this->container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(Widget::class),
            $this->entityManager->getClassMetadata(PurgeableLog::class),
            $this->entityManager->getClassMetadata(ConditionalPurgeableLog::class),
            $this->entityManager->getClassMetadata(RepeatablePurgeableLog::class),
        ]);

        $dispatcher = $this->container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(
            EntityPurgedEvent::NAME,
            function (EntityPurgedEvent $event): void { $this->events[] = $event; }
        );
    }

    public function testItPurgesRowsOlderThanTheRetentionAndKeepsTheRest(): void
    {
        $this->log('-4 months');
        $this->log('-2 months');

        $tester = $this->runPurge([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(1, $this->countRows('purgeable_log'));
        self::assertStringContainsString('1 entities purged', $tester->getDisplay());
    }

    public function testAnEntityWithoutAPolicyIsNeverTouched(): void
    {
        $this->entityManager->persist(new Widget('kept'));
        $this->entityManager->flush();

        $this->runPurge([]);

        self::assertSame(1, $this->countRows('widget'));
    }

    public function testNothingToPurgeIsReportedAsASuccess(): void
    {
        $this->log('-2 months');

        $tester = $this->runPurge([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Nothing to purge', $tester->getDisplay());
    }

    // ── --dry-run ─────────────────────────────────────────────────────────

    public function testDryRunDeletesNothingAndSaysWhatItWould(): void
    {
        $this->log('-4 months');

        $tester = $this->runPurge(['--dry-run' => true]);

        self::assertSame(1, $this->countRows('purgeable_log'));
        self::assertStringContainsString('DRY-RUN', $tester->getDisplay());
        self::assertStringContainsString('would be purged', $tester->getDisplay());
    }

    /** A dry run must not tell an audit trail that rows were purged. */
    public function testDryRunDispatchesNoEvent(): void
    {
        $this->log('-4 months');

        $this->runPurge(['--dry-run' => true]);

        self::assertSame([], $this->events);
    }

    // ── --entity ──────────────────────────────────────────────────────────

    public function testTheEntityOptionRestrictsThePurge(): void
    {
        $this->log('-4 months');
        $this->conditionalLog('-2 months', obsolete: true);

        $this->runPurge(['--entity' => 'ConditionalPurgeableLog']);

        self::assertSame(1, $this->countRows('purgeable_log'), 'The unrelated entity must survive.');
        self::assertSame(0, $this->countRows('conditional_purgeable_log'));
    }

    public function testAnUnknownEntityPurgesNothing(): void
    {
        $this->log('-4 months');

        $tester = $this->runPurge(['--entity' => 'NoSuchEntity']);

        self::assertSame(1, $this->countRows('purgeable_log'));
        self::assertStringContainsString('Nothing to purge', $tester->getDisplay());
    }

    // ── condition ExpressionLanguage ──────────────────────────────────────

    public function testTheConditionNarrowsThePurgeBeyondAge(): void
    {
        $this->conditionalLog('-2 months', obsolete: true);
        $this->conditionalLog('-2 months', obsolete: false);

        $this->runPurge([]);

        self::assertSame(1, $this->countRows('conditional_purgeable_log'), 'A row that is old but not obsolete must survive.');
    }

    // ── attribut répétable ────────────────────────────────────────────────

    public function testBothPoliciesOfARepeatableAttributeApply(): void
    {
        $this->repeatableLog('-3 years', null);                 // trop vieux → politique 1
        $this->repeatableLog('-1 day', '-2 weeks');             // supprimé depuis longtemps → politique 2
        $this->repeatableLog('-1 day', null);                   // ni l'un ni l'autre

        $this->runPurge([]);

        self::assertSame(1, $this->countRows('repeatable_purgeable_log'));
    }

    // ── événements ────────────────────────────────────────────────────────

    public function testOneEventIsDispatchedPerPurgedRowWithItsContext(): void
    {
        $log = $this->log('-4 months', organizationId: 7);
        $id = $log->getId();
        $this->log('-5 months');

        $this->runPurge([]);

        self::assertCount(2, $this->events);

        $matching = array_values(array_filter($this->events, static fn (EntityPurgedEvent $e): bool => $e->getEntityId() === $id));
        self::assertCount(1, $matching);
        self::assertSame(PurgeableLog::class, $matching[0]->getEntityClass());
        self::assertSame('PurgeableLog', $matching[0]->getEntityShortName());
        self::assertSame(7, $matching[0]->getOrganizationId());
        self::assertSame('-3 months', $matching[0]->getInterval());
    }

    /** An entity with no tenant accessor simply reports none. */
    public function testAnEntityWithoutATenantAccessorReportsNoOrganization(): void
    {
        $this->conditionalLog('-2 months', obsolete: true);

        $this->runPurge([]);

        self::assertCount(1, $this->events);
        self::assertNull($this->events[0]->getOrganizationId());
    }

    // ── verrou ────────────────────────────────────────────────────────────

    /**
     * A scheduled command must never run twice at once, and a prevented concurrent run is not
     * a failure: the cron should not page anyone.
     */
    public function testASecondInstanceExitsSuccessfullyWithoutPurging(): void
    {
        $this->log('-4 months');

        $factory = $this->container->get('lock.factory');
        self::assertInstanceOf(LockFactory::class, $factory);
        $held = $factory->createLock('core:purge', 3600);
        self::assertTrue($held->acquire());

        try {
            $tester = $this->runPurge([]);

            self::assertSame(0, $tester->getStatusCode());
            self::assertStringContainsString('Another instance is already running', $tester->getDisplay());
            self::assertSame(1, $this->countRows('purgeable_log'), 'The concurrent run must not have purged anything.');
        } finally {
            $held->release();
        }
    }

    // ── lots ──────────────────────────────────────────────────────────────

    /** More rows than the batch size, to exercise the intermediate flushes. */
    public function testItPurgesMoreRowsThanOneBatch(): void
    {
        for ($i = 0; $i < 7; ++$i) {
            $this->log('-4 months');
        }

        $this->runPurge([]);

        self::assertSame(0, $this->countRows('purgeable_log'));
        self::assertCount(7, $this->events);
    }

    // ── enregistrement conditionnel ───────────────────────────────────────

    /**
     * The command needs the entity manager; registering it unconditionally would break the
     * container of every application that installs the bundle without the ORM.
     */
    /** A project renaming the command keeps its deployed crontab working through an alias. */
    public function testTheConfiguredAliasIsApplied(): void
    {
        $command = $this->container->get(PurgeCommand::class);
        self::assertInstanceOf(PurgeCommand::class, $command);

        self::assertSame(['legacy:purge'], $command->getAliases());
    }

    public function testTheCommandIsNotRegisteredWithoutTheOrm(): void
    {
        self::assertFalse($this->boot()->has(PurgeCommand::class));
    }

    /** @param array<string, mixed> $input */
    private function runPurge(array $input): CommandTester
    {
        $command = $this->container->get(PurgeCommand::class);
        self::assertInstanceOf(PurgeCommand::class, $command);

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private function log(string $age, ?int $organizationId = null): PurgeableLog
    {
        $log = new PurgeableLog(new \DateTimeImmutable($age), $organizationId);
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    private function conditionalLog(string $age, bool $obsolete): void
    {
        $this->entityManager->persist(new ConditionalPurgeableLog(new \DateTimeImmutable($age), $obsolete));
        $this->entityManager->flush();
    }

    private function repeatableLog(string $createdAge, ?string $deletedAge): void
    {
        $this->entityManager->persist(new RepeatablePurgeableLog(
            new \DateTimeImmutable($createdAge),
            null !== $deletedAge ? new \DateTimeImmutable($deletedAge) : null,
        ));
        $this->entityManager->flush();
    }

    private function countRows(string $table): int
    {
        $count = $this->entityManager->getConnection()->executeQuery(\sprintf('SELECT COUNT(*) FROM %s', $table))->fetchOne();

        self::assertIsNumeric($count);

        return (int) $count;
    }
}
