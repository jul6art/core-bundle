<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Command;

use Jul6Art\CoreBundle\Performance\Service\PerformanceExporter;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'core:performance:export',
    description: 'Export the performance store to CSV or JSON.'
)]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly PerformanceStoreInterface $store,
        private readonly PerformanceExporter $exporter,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('format', InputArgument::OPTIONAL, 'Export format (csv|json)', 'csv')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (stdout if omitted)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $lock = $this->lockFactory->createLock('app:performance:export', ttl: 1800);
        if (!$lock->acquire()) {
            $io->note('Another instance is already running, exiting.');

            return Command::SUCCESS;
        }

        try {
            $format = (string) $input->getArgument('format');

            if (!\in_array($format, ['csv', 'json'], true)) {
                $io->error(\sprintf('Unsupported format "%s". Use csv or json.', $format));

                return Command::FAILURE;
            }

            $target = $input->getOption('output');
            $this->render($format, $target);

            if (null !== $target) {
                $io->success(\sprintf('Exported to %s.', $target));
            }

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function render(string $format, ?string $target): void
    {
        if (null === $target) {
            $this->exporter->stream($this->store->listAll(), $format);

            return;
        }

        ob_start();
        $this->exporter->stream($this->store->listAll(), $format);
        $content = (string) ob_get_clean();

        file_put_contents($target, $content);
    }
}
