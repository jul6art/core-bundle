<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Test;

use Jul6Art\CoreBundle\Translation\JsTranslationAudit;
use Jul6Art\CoreBundle\Translation\JsTranslationScanner;
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

        return new JsTranslationAudit($translator, static::domain())->audit($scan, $locales, static::declaredKeys());
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
