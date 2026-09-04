<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation;

use Jul6Art\CoreBundle\Translation\ServerDomainScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The scanner that catches the half of a domain move everyone forgets.
 *
 * ⚠️ Each case pins a shape this ecosystem actually writes. The two that matter most are the
 * NEGATIVE ones: an enum returning a bare key must not be reported (the domain belongs to its
 * caller), and neither must a call that already names the browser domain. A guard that shouts on
 * either is a guard someone switches off.
 */
#[CoversClass(ServerDomainScanner::class)]
final class ServerDomainScannerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/server-domain-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testABareTwigTransIsReported(): void
    {
        $this->write('show.html.twig', "<span>{{ 'status.active'|trans }}</span>");

        self::assertCount(1, $this->scan(['status.active']));
    }

    public function testATwigTransNamingTheBrowserDomainIsNotReported(): void
    {
        $this->write('show.html.twig', "<span>{{ 'status.active'|trans({}, 'javascript') }}</span>");

        self::assertSame([], $this->scan(['status.active']));
    }

    public function testAnExplicitOtherDomainIsReported(): void
    {
        $this->write('Provider.php', "<?php \$this->t('status.active', 'datatable');");

        $offenders = $this->scan(['status.active']);

        self::assertCount(1, $offenders);
        self::assertStringContainsString("'datatable'", $offenders[0]);
    }

    public function testTheThirdArgumentShapeIsReadToo(): void
    {
        $this->write('Controller.php', "<?php \$this->translator->trans('status.active', [], 'messages');");

        self::assertCount(1, $this->scan(['status.active']));
    }

    /**
     * ⚠️ The case that decides whether the guard is usable. `label()` returns a key and nothing
     * else — by design, because the domain belongs to whoever renders it. Reporting it would
     * drown the real defect under every enum of the project.
     */
    public function testAKeyReturnedWithNoDomainIsNotReported(): void
    {
        $this->write('Status.php', "<?php enum Status { public function label(): string { return 'status.active'; } }");

        self::assertSame([], $this->scan(['status.active']));
    }

    public function testTheReportNamesTheFileAndTheLine(): void
    {
        $this->write('show.html.twig', "<div>\n  <span>{{ 'status.active'|trans }}</span>\n</div>");

        $offenders = $this->scan(['status.active']);

        self::assertStringContainsString('show.html.twig:2', $offenders[0]);
        self::assertStringContainsString("{{ 'status.active'|trans }}", $offenders[0]);
    }

    public function testDoubleQuotedLiteralsCountToo(): void
    {
        $this->write('Provider.php', '<?php $this->t("status.active", "datatable");');

        self::assertCount(1, $this->scan(['status.active']));
    }

    /** A key nobody names server-side is the normal case, and it stays silent. */
    public function testAKeyReadOnlyByJavaScriptIsNotReported(): void
    {
        $this->write('show.html.twig', "<span>{{ 'other.key'|trans }}</span>");

        self::assertSame([], $this->scan(['status.active']));
    }

    /** No key to guard means no work, and no walk of the file tree either. */
    public function testAnEmptyKeyListScansNothing(): void
    {
        $this->write('show.html.twig', "<span>{{ 'status.active'|trans }}</span>");

        self::assertSame([], $this->scan([]));
    }

    public function testAMissingDirectoryContributesNothing(): void
    {
        self::assertSame(
            [],
            new ServerDomainScanner()->scan(['status.active'], [$this->directory.'/absent']),
        );
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function scan(array $keys): array
    {
        return new ServerDomainScanner()->scan($keys, [$this->directory]);
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents($this->directory.'/'.$name, $contents);
    }
}
