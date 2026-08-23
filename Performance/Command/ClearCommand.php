<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Command;

use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'core:performance:clear',
    description: 'Clear the performance store (all persisted records).'
)]
final class ClearCommand extends Command
{
    public function __construct(
        private readonly PerformanceStoreInterface $store,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $lock = $this->lockFactory->createLock('app:performance:clear', ttl: 300);
        if (!$lock->acquire()) {
            $io->note('Another instance is already running, exiting.');

            return Command::SUCCESS;
        }

        try {
            $count = $this->store->count();
            $this->store->clear();

            $io->success(\sprintf('Cleared %d performance records.', $count));

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
