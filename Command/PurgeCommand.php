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
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
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
        // Resolves an interval that names a parameter (`%app.retention%`). Optional so the
        // command still builds by hand; without it such an interval fails loudly below.
        private readonly ?ContainerBagInterface $parameters = null,
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
     * ⚠️ The rows are read ONE BATCH AT A TIME, and this is the whole point of the method.
     *
     * A single `getResult()` hydrates every expired row before deleting the first one: on three
     * years of notifications that is an out-of-memory error, at night, with nobody watching.
     * Flushing every `n` rows does not help — a flush empties the pending deletes, not the unit
     * of work, and the hydrated entities pile up until the last one.
     *
     * The cursor is the identifier (`e.id > :lastId`), not an offset: `LIMIT/OFFSET` walks past
     * rows that the previous batch has just deleted, and every batch would skip as many rows as
     * it removed. The cursor also makes `--dry-run` terminate — nothing is deleted there, so an
     * unmoved window would loop forever.
     *
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
        $threshold = new \DateTimeImmutable($this->interval($purgeable));
        $count = 0;
        $lastId = null;

        while (true) {
            $batch = $this->batch($className, $purgeable->field, $threshold, $lastId);

            if ([] === $batch) {
                break;
            }

            /** @var list<array{id: int|string|null, organizationId: int|null}> $purged */
            $purged = [];

            foreach ($batch as $entity) {
                // Read before anything else: the cursor must advance even on a row the
                // condition keeps, otherwise the next batch reads the same window forever.
                $lastId = self::identifierOf($entity);

                if ('' !== $purgeable->condition
                    && null !== $expressionLanguage
                    && true !== (bool) $expressionLanguage->evaluate($purgeable->condition, ['entity' => $entity])
                ) {
                    continue;
                }

                ++$count;

                if ($dryRun) {
                    $io->text(\sprintf('  [DRY-RUN] Would purge %s#%s', $shortName, $lastId ?? '?'));

                    continue;
                }

                // Collected before the remove: once flushed the entity is detached, and the
                // event has to name a row that no longer exists.
                $purged[] = ['id' => $lastId, 'organizationId' => self::organizationOf($entity)];
                $this->entityManager->remove($entity);
            }

            if (!$dryRun && [] !== $purged) {
                $this->entityManager->flush();
            }

            // The batch is gone from memory here, and only here: the events below carry
            // scalars, never the entities they name.
            $this->entityManager->clear();

            foreach ($purged as $row) {
                $this->eventDispatcher->dispatch(
                    new EntityPurgedEvent(
                        entityClass: $className,
                        entityShortName: $shortName,
                        entityId: $row['id'],
                        organizationId: $row['organizationId'],
                        interval: $this->interval($purgeable),
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
                $this->interval($purgeable),
                '' !== $purgeable->condition ? ', condition: '.$purgeable->condition : '',
            ));
        }

        return $count;
    }

    /**
     * One window of expired rows, ordered by identifier and starting after the last one seen.
     *
     * @return list<object>
     */
    /**
     * The interval as the purge applies it. An attribute argument must be a constant, so a
     * retention an operator changes (an audit trail kept 18 months, then 12) is written as a
     * parameter — `#[Purgeable(field: 'createdAt', interval: '%app.retention%')]` — and resolved
     * here, at run time: a parameter fed by `%env(...)%` then changes the purge with no rebuild
     * and no migration.
     */
    private function interval(Purgeable $purgeable): string
    {
        if (null === $this->parameters || !str_contains($purgeable->interval, '%')) {
            return $purgeable->interval;
        }

        $resolved = $this->parameters->resolveValue($purgeable->interval);

        if (!\is_string($resolved) || '' === trim($resolved)) {
            throw new \LogicException(\sprintf('The purge interval "%s" does not resolve to a non-empty string.', $purgeable->interval));
        }

        return $resolved;
    }

    private function batch(string $className, string $field, \DateTimeImmutable $threshold, int|string|null $lastId): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($className, 'e')
            ->where(\sprintf('e.%s < :threshold', $field))
            ->setParameter('threshold', $threshold)
            ->orderBy('e.id', \SortDirection::Ascending)
            ->setMaxResults($this->batchSize);

        if (null !== $lastId) {
            $builder->andWhere('e.id > :lastId')->setParameter('lastId', $lastId);
        }

        $rows = $builder->getQuery()->getResult();

        return \is_array($rows) ? array_values(array_filter($rows, \is_object(...))) : [];
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
