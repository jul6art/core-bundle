<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation;

use Jul6Art\CoreBundle\Translation\JsTranslationScan;
use Jul6Art\CoreBundle\Translation\JsTranslationScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Reads the translation keys a JavaScript file asks for.
 *
 * This is the half of the guard that looks at the code. Whether those keys exist is
 * {@see \Jul6Art\CoreBundle\Translation\JsTranslationAudit}'s question; the scanner only has to
 * find them all, and to be honest about the ones it cannot resolve.
 */
#[CoversClass(JsTranslationScanner::class)]
final class JsTranslationScannerTest extends TestCase
{
    public function testItFindsAKeyReadThroughTheMixin(): void
    {
        $scan = $this->scan("this.t('datatable.error.saving');");

        self::assertSame(['datatable.error.saving'], $scan->keys());
    }

    public function testItFindsAKeyReadThroughABareCall(): void
    {
        $scan = $this->scan("import { t } from '…';\nexport const label = () => t('user.role.admin');");

        self::assertSame(['user.role.admin'], $scan->keys());
    }

    public function testItFindsAKeyReadThroughTrans(): void
    {
        $scan = $this->scan("trans('modal.delete.title', {}, 'javascript');");

        self::assertSame(['modal.delete.title'], $scan->keys());
    }

    public function testItFindsAKeyOnAnyReceiver(): void
    {
        // The datatable renderers get the controller as `c`, not as `this`.
        $scan = $this->scan("return c.t('datatable.age.day_short');");

        self::assertSame(['datatable.age.day_short'], $scan->keys());
    }

    public function testDoubleQuotesCountToo(): void
    {
        $scan = $this->scan('this.t("datatable.filters");');

        self::assertSame(['datatable.filters'], $scan->keys());
    }

    /**
     * ⚠️ `array.at(` ends with `t(`. Without an anchor, every `at()`, `sort()` and `insert()` of
     * the codebase would be reported as a missing translation, and the guard would be turned off
     * within the week.
     */
    public function testItIgnoresMethodsWhoseNameMerelyEndsWithT(): void
    {
        $scan = $this->scan("rows.at(0); list.sort('name'); q.insert('x'); this.format('y');");

        self::assertSame([], $scan->keys());
        self::assertSame([], $scan->dynamicCalls());
    }

    public function testKeysAreReportedOnceWithEveryLocation(): void
    {
        $scan = $this->scan("this.t('datatable.filters');\nthis.t('datatable.filters');", 'a.js');

        self::assertSame(['datatable.filters'], $scan->keys());
        self::assertSame(['a.js:1', 'a.js:2'], $scan->occurrences('datatable.filters'));
    }

    /**
     * A template literal cannot be resolved to a key, but its constant head is still a promise:
     * everything under `datatable.modal.` is read by this call. The audit uses it to tell a dead
     * catalogue entry from one that is only reached at runtime.
     */
    public function testATemplateLiteralYieldsAPrefix(): void
    {
        $scan = $this->scan('this.t(`datatable.modal.${action.type}.${key}`);');

        self::assertSame([], $scan->keys());
        self::assertSame(['datatable.modal.'], $scan->prefixes());
    }

    public function testATemplateLiteralWithoutInterpolationIsAKey(): void
    {
        $scan = $this->scan('this.t(`datatable.filters`);');

        self::assertSame(['datatable.filters'], $scan->keys());
        self::assertSame([], $scan->prefixes());
    }

    /**
     * ⚠️ Reported, never swallowed. `this.t(labelKey)` is exactly how a key escapes every guard;
     * naming its location is the only way the reader can decide whether it is safe.
     */
    public function testAVariableArgumentIsReportedAsDynamic(): void
    {
        $scan = $this->scan('this.t(labelKey);', 'b.js');

        self::assertSame([], $scan->keys());
        self::assertSame(['b.js:1'], $scan->dynamicCalls());
    }

    /**
     * ⚠️ `` c.t(`${labelPath}.${key}`) `` — a real call in the datatable renderers. Its constant
     * head is empty, so it promises nothing at all; filing it as a prefix would make it vouch for
     * the entire catalogue and every dead key would look alive. It is a dynamic call.
     */
    public function testATemplateLiteralWithNoConstantHeadIsDynamic(): void
    {
        $scan = $this->scan('const label = c.t(`${labelPath}.${key}`);', 'c.js');

        self::assertSame([], $scan->prefixes());
        self::assertSame(['c.js:1'], $scan->dynamicCalls());
    }

    /**
     * ⚠️ The mixin that *defines* `t(key) {` is not a site that *reads* a key. Reporting it as a
     * dynamic call puts a permanent false entry in every report — the kind of noise that teaches
     * a reader to skip the section.
     */
    public function testAMethodDeclarationIsNotACall(): void
    {
        $source = <<<'JS'
            export function useTranslatable(controller) {
                Object.assign(controller, {
                    t(key) {
                        return key;
                    },
                });
            }
            JS;

        $scan = $this->scan($source);

        self::assertSame([], $scan->keys());
        self::assertSame([], $scan->dynamicCalls());
    }

    public function testAPrefixCoversTheKeysBelowIt(): void
    {
        $scan = $this->scan('this.t(`datatable.modal.${type}.title`);');

        self::assertTrue($scan->covers('datatable.modal.delete.title'));
        self::assertFalse($scan->covers('datatable.filters'));
    }

    public function testALiteralKeyIsCoveredByItself(): void
    {
        self::assertTrue($this->scan("this.t('datatable.filters');")->covers('datatable.filters'));
    }

    public function testCommentsAreNotCode(): void
    {
        $source = <<<'JS'
            // Historique : this.t('gone.away') a été retiré le 2026-01-01.
            /* this.t('also.gone') */
            this.t('still.here');
            JS;

        self::assertSame(['still.here'], $this->scan($source)->keys());
    }

    public function testItWalksADirectoryAndKeepsOnlyJavaScript(): void
    {
        $dir = $this->fixtureDirectory([
            'controllers/a_controller.js' => "this.t('one');",
            'nested/deep/b.js' => "this.t('two');",
            'styles/app.css' => "this.t('not.a.key');",
            'README.md' => "this.t('not.a.key.either');",
        ]);

        $scan = new JsTranslationScanner()->scan($dir);

        self::assertSame(['one', 'two'], $scan->keys());
    }

    /**
     * ⚠️ `assets/vendor/@hotwired/turbo/turbo.index.js` is minified third-party code, and a
     * minified bundle is a field of one-letter identifiers: scanning it reported sixteen dynamic
     * calls in `cereezer` alone. Vendored trees are not this project's translations.
     */
    public function testVendoredTreesAreSkipped(): void
    {
        $dir = $this->fixtureDirectory([
            'app.js' => "this.t('mine');",
            'vendor/turbo.index.js' => 'const x = e.t(n);',
            'node_modules/dep/index.js' => "this.t('theirs');",
            'dist/bundle.js' => "this.t('built');",
        ]);

        $scan = new JsTranslationScanner()->scan($dir);

        self::assertSame(['mine'], $scan->keys());
        self::assertSame([], $scan->dynamicCalls());
    }

    public function testTheSkippedDirectoriesAreConfigurable(): void
    {
        $dir = $this->fixtureDirectory([
            'app.js' => "this.t('mine');",
            'vendor/lib.js' => "this.t('vendored');",
        ]);

        $scan = new JsTranslationScanner(excludedDirectories: [])->scan($dir);

        self::assertSame(['mine', 'vendored'], $scan->keys());
    }

    public function testScanningAMissingDirectoryYieldsNothing(): void
    {
        $scan = new JsTranslationScanner()->scan(sys_get_temp_dir().'/jul6art-core-bundle-does-not-exist');

        self::assertSame([], $scan->keys());
    }

    private function scan(string $source, string $origin = 'test.js'): JsTranslationScan
    {
        return new JsTranslationScanner()->scanSource($source, $origin);
    }

    /**
     * @param array<string, string> $files relative path => contents
     */
    private function fixtureDirectory(array $files): string
    {
        $root = sys_get_temp_dir().'/jul6art-core-bundle-scan-'.bin2hex(random_bytes(6));

        foreach ($files as $path => $contents) {
            $full = $root.'/'.$path;
            $directory = \dirname($full);

            if (!is_dir($directory)) {
                mkdir($directory, 0o777, true);
            }

            file_put_contents($full, $contents);
        }

        return $root;
    }
}
