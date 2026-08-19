<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\CoreBundle\Attribute\Purgeable;
use Jul6Art\CoreBundle\Event\EntityPurgedEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Removes the rows whose retention policy has expired, as declared by {@see Purgeable}.
 *
 * ```shell
 * bin/console core:purge
 * bin/console core:purge --dry-run
 * bin/console core:purge --entity=AuditLog
 * ```
 *
 * The command is registered only when the ORM and a lock factory are both available — see
 * the bundle extension. It takes a lock before doing any work: two concurrent purges would
 * race on the same rows, and a prevented concurrent run exits `SUCCESS` so a scheduler does
 * not page anyone for a guard working as intended.
 *
 * It writes no audit trail of its own; it dispatches one {@see EntityPurgedEvent} per removed
 * row, after the flush, and lets the application record what it wants.
 */
#[AsCommand(
    name: 'core:purge',
    description: 'Purge expired entities based on #[Purgeable] retention policies.',
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LockFactory $lockFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly int $batchSize = 100,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be purged without deleting')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Purge only this entity (short class name, e.g. AuditLog)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Daily purge: a 1h TTL sits comfortably above the worst case (a large tenant with
        // many expired rows and batched flushes) and well below the interval between runs.
        $lock = $this->lockFactory->createLock('core:purge', ttl: 3600);

        if (!$lock->acquire()) {
            $io->note('Another instance is already running, exiting.');

            return Command::SUCCESS;
        }

        try {
            return $this->purge($io, (bool) $input->getOption('dry-run'), $this->entityFilter($input));
        } finally {
            $lock->release();
        }
    }

    private function entityFilter(InputInterface $input): ?string
    {
        $filter = $input->getOption('entity');

        return \is_string($filter) && '' !== $filter ? $filter : null;
    }

    private function purge(SymfonyStyle $io, bool $dryRun, ?string $entityFilter): int
    {
        if ($dryRun) {
            $io->note('Dry-run mode — nothing will be deleted.');
        }

        $expressionLanguage = null;
        $total = 0;

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $reflection = new \ReflectionClass($metadata->getName());
            $attributes = $reflection->getAttributes(Purgeable::class);

            if ([] === $attributes) {
                continue;
            }

            $shortName = $reflection->getShortName();

            if (null !== $entityFilter && $shortName !== $entityFilter) {
                continue;
            }

            foreach ($attributes as $attribute) {
                $purgeable = $attribute->newInstance();

                // The expression engine is only built when a policy actually needs one, so
                // symfony/expression-language stays a suggestion rather than a requirement.
                if ('' !== $purgeable->condition) {
                    $expressionLanguage ??= new ExpressionLanguage();
                }

                $total += $this->purgeOne($io, $metadata->getName(), $shortName, $purgeable, $expressionLanguage, $dryRun);
            }
        }

        if (0 === $total) {
            $io->success('Nothing to purge.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d entities %s.', $total, $dryRun ? 'would be purged' : 'purged'));

        return Command::SUCCESS;
    }

    /**
     * @param class-string $className
     */
    private function purgeOne(
        SymfonyStyle $io,
        string $className,
        string $shortName,
        Purgeable $purgeable,
        ?ExpressionLanguage $expressionLanguage,
        bool $dryRun,
    ): int {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($className, 'e')
            ->where(\sprintf('e.%s < :threshold', $purgeable->field))
            ->setParameter('threshold', new \DateTimeImmutable($purgeable->interval))
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        if (!\is_array($entities)) {
            return 0;
        }

        $count = 0;
        /** @var list<array{id: int|string|null, organizationId: int|null}> $purged */
        $purged = [];

        foreach ($entities as $entity) {
            if (!\is_object($entity)) {
                continue;
            }

            if ('' !== $purgeable->condition
                && null !== $expressionLanguage
                && true !== (bool) $expressionLanguage->evaluate($purgeable->condition, ['entity' => $entity])
            ) {
                continue;
            }

            ++$count;

            if ($dryRun) {
                $io->text(\sprintf('  [DRY-RUN] Would purge %s#%s', $shortName, self::identifierOf($entity) ?? '?'));

                continue;
            }

            // Collected before the remove: once flushed the entity is detached, and the
            // event has to name a row that no longer exists.
            $purged[] = ['id' => self::identifierOf($entity), 'organizationId' => self::organizationOf($entity)];
            $this->entityManager->remove($entity);

            if (0 === $count % $this->batchSize) {
                $this->entityManager->flush();
            }
        }

        if (!$dryRun && $count > 0) {
            $this->entityManager->flush();

            foreach ($purged as $row) {
                $this->eventDispatcher->dispatch(
                    new EntityPurgedEvent(
                        entityClass: $className,
                        entityShortName: $shortName,
                        entityId: $row['id'],
                        organizationId: $row['organizationId'],
                        interval: $purgeable->interval,
                        condition: $purgeable->condition,
                    ),
                    EntityPurgedEvent::NAME,
                );
            }
        }

        if ($count > 0) {
            $io->info(\sprintf(
                '%s: %d entities %s (field: %s, interval: %s%s)',
                $shortName,
                $count,
                $dryRun ? 'to purge' : 'purged',
                $purgeable->field,
                $purgeable->interval,
                '' !== $purgeable->condition ? ', condition: '.$purgeable->condition : '',
            ));
        }

        return $count;
    }

    private static function identifierOf(object $entity): int|string|null
    {
        if (!method_exists($entity, 'getId')) {
            return null;
        }

        $id = $entity->getId();

        return \is_int($id) || \is_string($id) ? $id : null;
    }

    private static function organizationOf(object $entity): ?int
    {
        if (!method_exists($entity, 'getOrganizationId')) {
            return null;
        }

        $id = $entity->getOrganizationId();

        return \is_int($id) ? $id : null;
    }
}
