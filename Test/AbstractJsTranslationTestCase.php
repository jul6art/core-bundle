<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Test;

use Jul6Art\CoreBundle\Translation\JsTranslationAudit;
use Jul6Art\CoreBundle\Translation\JsTranslationScanner;
use Jul6Art\CoreBundle\Translation\ServerDomainScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * The guard every project of this ecosystem extends once, and never thinks about again.
 *
 * ## What it exists to catch
 *
 * Before `symfony/ux-translator`, a label reached JavaScript through a tree built in Twig and
 * walked in JS. Nothing tied the two halves together, and the ecosystem shipped a defect for
 * months because of it: the datatable controller read `bulk.select_all` while the Twig partial
 * sent `datatable.bulk.select_all`, so every bulk-selection checkbox of three back-offices
 * carried the literal string "bulk.select_all" as its aria-label. The bundle's own test looked
 * at each half in its own file, never one against the other, and stayed green throughout.
 *
 * With the catalogue as the single source, the JS key IS the catalogue key — and one test can
 * hold both ends.
 *
 * ## How to use it
 *
 * ```php
 * final class JsTranslationTest extends AbstractJsTranslationTestCase
 * {
 *     protected static function javaScriptDirectories(): array
 *     {
 *         return [
 *             self::projectDir().'/assets',
 *             // ⚠️ The bundles too: their controllers read keys THIS catalogue must hold.
 *             self::projectDir().'/vendor/jul6art/datatable-bundle/assets',
 *         ];
 *     }
 *
 *     protected static function declaredKeys(): array
 *     {
 *         return array_map(static fn (WorkOrderStatus $c): string => $c->translationKey(), WorkOrderStatus::cases());
 *     }
 *
 *     protected static function templateDirectories(): array
 *     {
 *         return [self::projectDir().'/templates'];
 *     }
 * }
 * ```
 */
abstract class AbstractJsTranslationTestCase extends KernelTestCase
{
    /**
     * ⚠️ Left as a literal string on purpose. Deriving it from the controller identifier would
     * make the guard follow a rename of the very thing it forbids.
     */
    private const string LEGACY_ATTRIBUTE = '-translations-value';

    /**
     * Every directory whose JavaScript reads keys this catalogue must hold — the project's own,
     * and the assets of every bundle it installs.
     *
     * @return list<string>
     */
    abstract protected static function javaScriptDirectories(): array;

    public function testEveryKeyReadByJavaScriptIsTranslated(): void
    {
        $report = $this->report();

        self::assertSame([], $report->missing(), "\n".$report->describe());
    }

    /**
     * The other direction, and the one only this domain makes worth asking: a key nothing reads
     * is weight shipped to every visitor of every page, in every locale.
     */
    public function testNoKeyOfTheDomainIsReadByNothing(): void
    {
        $report = $this->report();

        self::assertSame([], $report->unused(), "\n".$report->describe());
    }

    /**
     * The anti-regression guard: the day the last `data-…-translations-value` disappears, this
     * makes sure none comes back.
     *
     * It skips itself while {@see self::templateDirectories()} is empty, which is how a project
     * mid-migration keeps a green suite — declare the directories when the last attribute is
     * gone, not before.
     */
    public function testNoTemplateStillShipsATranslationsAttribute(): void
    {
        $directories = static::templateDirectories();

        if ([] === $directories) {
            self::markTestSkipped('Declare templateDirectories() once the last data-…-translations-value is gone.');
        }

        $offenders = [];

        foreach ($directories as $directory) {
            foreach (self::templatesIn($directory) as $template) {
                if (str_contains((string) file_get_contents($template), self::LEGACY_ATTRIBUTE)) {
                    $offenders[] = $template;
                }
            }
        }

        self::assertSame([], $offenders, \sprintf(
            "These templates still hand labels to JavaScript through an HTML attribute:\n  - %s",
            implode("\n  - ", $offenders),
        ));
    }

    /**
     * The other half of a domain move: the callers left behind.
     *
     * ⚠️ Moving a vocabulary into the browser domain is the easy half. The same status is also
     * rendered server-side — a chip on the record page, an option in a filter — and a `|trans`
     * left on the old domain does not fail: it renders the KEY, in full, in the page. That
     * shipped twice in this ecosystem, and both times a screen test found it months later by
     * looking for a translated word in the HTML.
     *
     * Only keys the browser domain holds ALONE are checked. A key both catalogues define is
     * ambiguous by construction, and reporting it would make the guard cry wolf on the very
     * vocabulary a project chose to keep in two places.
     *
     * It skips itself while {@see self::serverDirectories()} is empty — the same way the
     * attribute guard does, so a project mid-migration keeps a green suite.
     */
    public function testNoServerCallerAsksTheWrongDomain(): void
    {
        $directories = static::serverDirectories();

        if ([] === $directories) {
            self::markTestSkipped('Declare serverDirectories() to guard the server-side callers of the domain.');
        }

        $offenders = new ServerDomainScanner(static::domain())->scan($this->exclusiveKeys(), $directories);

        self::assertSame([], $offenders, \sprintf(
            "These call sites ask for a key of the \"%s\" domain in another catalogue.\n"
            ."A wrong domain does not fail — it renders the key, in full, in the page:\n  - %s",
            static::domain(),
            implode("\n  - ", $offenders),
        ));
    }

    /**
     * Server-side code checked by {@see self::testNoServerCallerAsksTheWrongDomain()} — the
     * templates and the PHP that render the same vocabulary the browser reads.
     *
     * @return list<string>
     */
    protected static function serverDirectories(): array
    {
        return [];
    }

    /**
     * The keys the browser domain holds and no other catalogue does, in the first locale served.
     *
     * ⚠️ One locale is enough, and more would be misleading: a key present in `javascript.fr`
     * and in `messages.en` is a catalogue that has drifted, which is
     * {@see self::testEveryKeyReadByJavaScriptIsTranslated()}'s business, not this guard's.
     *
     * @return list<string>
     */
    private function exclusiveKeys(): array
    {
        self::bootKernel();

        $translator = static::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        $locales = static::locales();
        self::assertNotEmpty($locales, 'Set framework.enabled_locales, or override locales().');

        $catalogue = $translator->getCatalogue($locales[0]);
        $domain = static::domain();
        $elsewhere = [];

        foreach ($catalogue->getDomains() as $other) {
            // ⚠️ `MessageCatalogueInterface::getDomains()` ne promet qu'un `array` : rien sur le
            // type de ses éléments. La vérification n'est pas décorative — c'est le seul endroit
            // où un type entre dans ce garde, et le seul où l'interface ne le garantit pas.
            if (!\is_string($other) || $other === $domain) {
                continue;
            }

            $elsewhere += $catalogue->all($other);
        }

        return array_values(array_diff(array_keys($catalogue->all($domain)), array_keys($elsewhere)));
    }

    /**
     * Keys the project vouches for without any scanner being able to see them — an enum's cases,
     * typically, read as `` t(`datatable.work_order_status.${value}`) ``.
     *
     * They are required to exist, and they are never reported as dead.
     *
     * @return list<string>
     */
    protected static function declaredKeys(): array
    {
        return [];
    }

    /**
     * A weaker promise than {@see self::declaredKeys()}: everything under the prefix is alive,
     * and nothing under it is required.
     *
     * ⚠️ For keys a controller reads *optionally*. The confirmation modals of `datatable-bundle`
     * look up `datatable.modal.<action>.<field>` and fall back to a generic text when it is
     * absent — the absence is designed. Declared as keys, every action type a project leaves
     * generic would be reported missing; declared nowhere, every type it customises would be
     * reported dead.
     *
     * @return list<string>
     */
    protected static function declaredPrefixes(): array
    {
        return [];
    }

    /**
     * Templates checked by {@see self::testNoTemplateStillShipsATranslationsAttribute()}.
     *
     * @return list<string>
     */
    protected static function templateDirectories(): array
    {
        return [];
    }

    /**
     * Every locale the application serves. Defaults to `framework.enabled_locales`.
     *
     * ⚠️ An application that leaves `enabled_locales` unset gets `[]` here, and a guard over no
     * locale proves nothing — hence the assertion rather than a quiet pass.
     *
     * @return list<string>
     */
    protected static function locales(): array
    {
        /** @var list<string> $locales */
        $locales = static::getContainer()->getParameter('kernel.enabled_locales');

        return $locales;
    }

    protected static function domain(): string
    {
        return self::stringParameter('core.js_translations.domain');
    }

    protected static function projectDir(): string
    {
        return self::stringParameter('kernel.project_dir');
    }

    /**
     * ⚠️ Asserted, not cast. A container parameter is `array|bool|float|int|string|null`, and a
     * cast would turn a miswired one into an empty string — which reads, downstream, as "the
     * domain is called nothing" rather than as the configuration mistake it is.
     */
    private static function stringParameter(string $name): string
    {
        $value = static::getContainer()->getParameter($name);

        self::assertIsString($value, \sprintf('Container parameter "%s" should be a string.', $name));

        return $value;
    }

    private function report(): \Jul6Art\CoreBundle\Translation\JsTranslationReport
    {
        self::bootKernel();

        $translator = static::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        $locales = static::locales();
        self::assertNotEmpty($locales, 'Set framework.enabled_locales, or override locales(): a guard over no locale proves nothing.');

        $directories = static::javaScriptDirectories();
        self::assertNotEmpty($directories, 'javaScriptDirectories() is empty: there is nothing to guard.');

        $scan = new JsTranslationScanner()->scan(...$directories);

        return new JsTranslationAudit($translator, static::domain())
            ->audit($scan, $locales, static::declaredKeys(), static::declaredPrefixes());
    }

    /**
     * @return list<string>
     */
    private static function templatesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        $templates = [];

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                $templates[] = $file->getPathname();
            }
        }

        sort($templates);

        return $templates;
    }
}
