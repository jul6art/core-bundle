<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Command;

use Jul6Art\CoreBundle\Translation\JsTranslationAudit;
use Jul6Art\CoreBundle\Translation\JsTranslationScanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Reads the JavaScript, reads the catalogue, and says where they disagree.
 *
 * ```shell
 * bin/console core:i18n:js-audit assets vendor/jul6art/datatable-bundle/assets
 * ```
 *
 * `AbstractJsTranslationTestCase` asks the same question of a build. This asks it of a developer,
 * which during a migration is the more useful of the two: "these keys are read by JavaScript and
 * are not in `javascript` yet" is a list of things to move, where a red suite is only a verdict.
 */
#[AsCommand(
    name: 'core:i18n:js-audit',
    description: 'Compares the translation keys JavaScript reads with the ones the catalogue holds',
)]
final class JsTranslationAuditCommand extends Command
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private readonly TranslatorBagInterface $translator,
        private readonly string $projectDir,
        private readonly string $domain,
        private readonly array $enabledLocales,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'directories',
                InputArgument::IS_ARRAY,
                'Directories to scan, relative to the project or absolute. ⚠️ Name the bundles too: their controllers read keys THIS catalogue must hold.',
                ['assets'],
            )
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Translation domain to audit', $this->domain)
            ->addOption('locale', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Locales to check (default: framework.enabled_locales)')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $domain = (string) $input->getOption('domain');
        /** @var list<string> $locales */
        $locales = $input->getOption('locale') ?: $this->enabledLocales;

        if ([] === $locales) {
            $io->error('No locale to check. Set framework.enabled_locales, or pass --locale.');

            return Command::INVALID;
        }

        $directories = array_map($this->absolute(...), self::directories($input));

        $io->title(\sprintf('Domain "%s" — locales %s', $domain, implode(', ', $locales)));
        $io->listing($directories);

        $scan = new JsTranslationScanner()->scan(...$directories);
        $report = new JsTranslationAudit($this->translator, $domain)->audit($scan, $locales);

        $this->reportMissing($io, $report->missing(), $scan);
        $this->reportUnused($io, $report->unused());
        $this->reportDynamic($io, $report->dynamicCalls());

        if ($report->isClean()) {
            $io->success(\sprintf('%d key(s) read, all translated, none unread.', \count($scan->keys())));

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    /**
     * @return list<string>
     */
    private static function directories(InputInterface $input): array
    {
        /** @var list<string> $directories */
        $directories = $input->getArgument('directories');

        return $directories;
    }

    /**
     * @param array<string, list<string>> $missing
     */
    private function reportMissing(SymfonyStyle $io, array $missing, \Jul6Art\CoreBundle\Translation\JsTranslationScan $scan): void
    {
        if ([] === $missing) {
            return;
        }

        $io->section(\sprintf('%d key(s) read by JavaScript, absent from the catalogue', \count($missing)));
        $io->table(
            ['Key', 'Missing in', 'Read at'],
            array_map(
                static fn (string $key, array $locales): array => [$key, implode(', ', $locales), implode("\n", $scan->occurrences($key))],
                array_keys($missing),
                $missing,
            ),
        );
    }

    /**
     * @param list<string> $unused
     */
    private function reportUnused(SymfonyStyle $io, array $unused): void
    {
        if ([] === $unused) {
            return;
        }

        $io->section(\sprintf('%d key(s) of the domain that nothing reads', \count($unused)));
        $io->listing($unused);
        $io->comment('A key derived from an enum is read through a template literal the scanner cannot resolve. Declare those in declaredKeys() of your test case, and leave them here.');
    }

    /**
     * @param list<string> $dynamic
     */
    private function reportDynamic(SymfonyStyle $io, array $dynamic): void
    {
        if ([] === $dynamic) {
            return;
        }

        $io->section(\sprintf('%d call(s) whose key could not be read', \count($dynamic)));
        $io->listing($dynamic);
        $io->comment('Not a defect: `t(labelKey)` is legitimate. Listed so nobody has to assume they are covered.');
    }

    private function absolute(string $directory): string
    {
        return str_starts_with($directory, '/') ? $directory : $this->projectDir.'/'.$directory;
    }
}
