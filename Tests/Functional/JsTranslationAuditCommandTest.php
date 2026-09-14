<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Jul6Art\CoreBundle\Command\JsTranslationAuditCommand;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `core:i18n:js-audit` — the same audit as the test guard, in a shape you can run while you work.
 *
 * The guard tells a build it is broken; the command tells a developer what to move. During a
 * migration that difference is the whole value: "these 41 keys are read by JavaScript and are
 * not in `javascript` yet" is a to-do list, a red suite is not.
 */
#[CoversNothing]
final class JsTranslationAuditCommandTest extends AbstractFunctionalTestCase
{
    public function testACleanProjectSucceeds(): void
    {
        $tester = $this->audit([__DIR__.'/../Fixtures/assets']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('javascript', $tester->getDisplay());
    }

    public function testAMissingKeyIsNamedWithItsCallSite(): void
    {
        $directory = $this->sources(['probe.js' => "this.t('never.translated');"]);

        $tester = $this->audit([$directory]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('never.translated', $tester->getDisplay());
        self::assertStringContainsString('probe.js:1', $tester->getDisplay());
    }

    /**
     * A key nobody reads is the failure mode a single-domain setup is meant to make visible: it
     * is shipped to every visitor and nothing else in the toolchain would ever mention it.
     */
    public function testAnUnreadKeyIsReported(): void
    {
        $tester = $this->audit([$this->sources(['empty.js' => '// nothing here'])]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('datatable.filters', $tester->getDisplay());
    }

    /**
     * ⚠️ Dynamic calls are listed and never fail the command. `this.t(labelKey)` is legitimate
     * code — the reader decides. A tool that refused it would be a tool people stop running.
     */
    public function testDynamicCallsAreListedWithoutFailing(): void
    {
        $directory = $this->sources([
            'dyn.js' => "this.t('datatable.filters');\nthis.t('datatable.error.saving');\nthis.t(labelKey);",
        ]);

        $tester = $this->audit([$directory]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('dyn.js:3', $tester->getDisplay());
    }

    public function testTheDomainCanBeOverridden(): void
    {
        $tester = $this->audit([$this->sources(['a.js' => "this.t('legacy.label');"])], ['--domain' => 'legacy']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testTheLocalesCanBeNarrowed(): void
    {
        // `front.fr.yaml` has no English counterpart: auditing both locales fails, `fr` alone does not.
        $sources = $this->sources(['a.js' => "this.t('front.label');"]);

        self::assertSame(Command::FAILURE, $this->audit([$sources], ['--domain' => 'front'])->getStatusCode());
        self::assertSame(Command::SUCCESS, $this->audit([$sources], ['--domain' => 'front', '--locale' => ['fr']])->getStatusCode());
    }

    /**
     * @param list<string>         $directories
     * @param array<string, mixed> $options
     */
    private function audit(array $directories, array $options = []): CommandTester
    {
        $container = $this->boot();

        $command = $container->get(JsTranslationAuditCommand::class);
        self::assertInstanceOf(JsTranslationAuditCommand::class, $command);

        $tester = new CommandTester($command);
        $tester->execute(['directories' => $directories, ...$options]);

        return $tester;
    }

    /**
     * @param array<string, string> $files
     */
    private function sources(array $files): string
    {
        $root = sys_get_temp_dir().'/jul6art-core-bundle-tests/audit-'.bin2hex(random_bytes(6));
        mkdir($root, 0o777, true);

        foreach ($files as $name => $contents) {
            file_put_contents($root.'/'.$name, $contents);
        }

        return $root;
    }
}
