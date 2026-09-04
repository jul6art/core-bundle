<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation;

use Jul6Art\CoreBundle\Translation\JsTranslationAudit;
use Jul6Art\CoreBundle\Translation\JsTranslationReport;
use Jul6Art\CoreBundle\Translation\JsTranslationScan;
use Jul6Art\CoreBundle\Translation\JsTranslationScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Confronts the keys the code reads with the keys the catalogue holds, in the one domain the
 * browser is allowed to see.
 *
 * Both directions matter. A key the code reads and the catalogue lacks shows up raw on a user's
 * screen; a key the catalogue holds and nothing reads is dead weight shipped to every visitor —
 * and, in a domain whose whole purpose is to bound what leaves the server, dead weight is the
 * failure mode worth naming.
 */
#[CoversClass(JsTranslationAudit::class)]
final class JsTranslationAuditTest extends TestCase
{
    public function testAKeyReadByTheCodeAndHeldByTheCatalogueIsClean(): void
    {
        $report = $this->audit(
            scanned: ['datatable.filters'],
            catalogue: ['fr' => ['datatable.filters' => 'Filtres'], 'en' => ['datatable.filters' => 'Filters']],
        );

        self::assertTrue($report->isClean());
        self::assertSame([], $report->missing());
        self::assertSame([], $report->unused());
    }

    public function testAKeyTheCodeReadsButTheCatalogueLacksIsMissing(): void
    {
        $report = $this->audit(
            scanned: ['datatable.filters'],
            catalogue: ['fr' => [], 'en' => []],
        );

        self::assertFalse($report->isClean());
        self::assertSame(['datatable.filters' => ['en', 'fr']], $report->missing());
    }

    /**
     * ⚠️ The catalogue's `has()` walks the fallback, so a key present in `en` answers yes for
     * `fr` too. That is right at runtime and wrong here: it is exactly what hides an untranslated
     * locale — `cegeta` ships five, three of them barely started. The audit asks `defines()`.
     */
    public function testAKeyMissingFromOneLocaleOnlyIsReportedForThatLocale(): void
    {
        $report = $this->audit(
            scanned: ['datatable.filters'],
            catalogue: ['en' => ['datatable.filters' => 'Filters'], 'fr' => []],
            fallbacks: ['fr' => 'en'],
        );

        self::assertSame(['datatable.filters' => ['fr']], $report->missing());
    }

    public function testAKeyNothingReadsIsUnused(): void
    {
        $report = $this->audit(
            scanned: [],
            catalogue: ['fr' => ['datatable.gone' => 'Parti'], 'en' => ['datatable.gone' => 'Gone']],
        );

        self::assertFalse($report->isClean());
        self::assertSame(['datatable.gone'], $report->unused());
    }

    /**
     * A template literal cannot name its keys, but it vouches for them. Without this, every
     * status label of a datatable would be reported dead on the first run.
     */
    public function testAKeyCoveredByAPrefixIsNotUnused(): void
    {
        $scan = new JsTranslationScanner()->scanSource('this.t(`datatable.modal.${type}.title`);', 'a.js');

        $report = new JsTranslationAudit($this->translator([
            'fr' => ['datatable.modal.delete.title' => 'Supprimer ?'],
        ]))->audit($scan, ['fr']);

        self::assertSame([], $report->unused());
    }

    /**
     * The enum vocabularies: a project derives them from its own cases and hands them over, since
     * no scanner can guess `WorkOrderStatus::cases()`.
     */
    public function testDeclaredKeysAreExpectedAndNeverUnused(): void
    {
        $report = $this->audit(
            scanned: [],
            catalogue: ['fr' => ['datatable.work_order_status.draft' => 'Brouillon']],
            locales: ['fr'],
            declared: ['datatable.work_order_status.draft', 'datatable.work_order_status.done'],
        );

        self::assertSame([], $report->unused());
        self::assertSame(['datatable.work_order_status.done' => ['fr']], $report->missing());
    }

    /**
     * A prefix the project vouches for: everything under it is alive, and nothing under it is
     * required.
     *
     * ⚠️ This is not the same promise as {@see self::testDeclaredKeysAreExpectedAndNeverUnused()},
     * and the difference came out of the datatable bundle. Its confirmation modals read
     * `datatable.modal.<action>.<field>` and FALL BACK to a generic text when the key is absent —
     * the absence is a designed behaviour, not an oversight. Declared as keys, every action type a
     * project does not customise would be reported missing; not declared at all, every one it does
     * customise would be reported dead.
     */
    public function testDeclaredPrefixesCoverWithoutRequiring(): void
    {
        $report = new JsTranslationAudit($this->translator([
            'fr' => ['datatable.modal.delete.title' => 'Supprimer ?'],
        ]))->audit(new JsTranslationScan(), ['fr'], [], ['datatable.modal.']);

        self::assertSame([], $report->unused());
        self::assertSame([], $report->missing());
        self::assertTrue($report->isClean());
    }

    public function testAKeyOutsideEveryDeclaredPrefixIsStillUnused(): void
    {
        $report = new JsTranslationAudit($this->translator([
            'fr' => ['datatable.gone' => 'Parti'],
        ]))->audit(new JsTranslationScan(), ['fr'], [], ['datatable.modal.']);

        self::assertSame(['datatable.gone'], $report->unused());
    }

    public function testDynamicCallsAreCarriedThroughToTheReport(): void
    {
        $scan = new JsTranslationScanner()->scanSource('this.t(labelKey);', 'a.js');

        $report = new JsTranslationAudit($this->translator(['fr' => []]))->audit($scan, ['fr']);

        self::assertSame(['a.js:1'], $report->dynamicCalls());
    }

    /**
     * ⚠️ A dynamic call is not a failure. It is a fact the reader has to weigh — a guard that
     * turned `this.t(labelKey)` into a red build would be a guard nobody keeps.
     */
    public function testDynamicCallsAloneDoNotFailTheAudit(): void
    {
        $scan = new JsTranslationScanner()->scanSource('this.t(labelKey);', 'a.js');

        self::assertTrue(new JsTranslationAudit($this->translator(['fr' => []]))->audit($scan, ['fr'])->isClean());
    }

    public function testTheDomainIsConfigurable(): void
    {
        $catalogue = new MessageCatalogue('fr', ['legacy' => ['a.key' => 'Valeur']]);

        $report = new JsTranslationAudit($this->bag([$catalogue]), 'legacy')
            ->audit(new JsTranslationScanner()->scanSource("this.t('a.key');", 'a.js'), ['fr']);

        self::assertTrue($report->isClean());
    }

    public function testTheDescriptionNamesTheKeyAndWhereItIsRead(): void
    {
        $scan = new JsTranslationScanner()->scanSource("this.t('datatable.filters');", 'app.js');

        $description = new JsTranslationAudit($this->translator(['fr' => []]))->audit($scan, ['fr'])->describe();

        self::assertStringContainsString('datatable.filters', $description);
        self::assertStringContainsString('app.js:1', $description);
        self::assertStringContainsString('fr', $description);
    }

    /**
     * @param list<string>                        $scanned
     * @param array<string, array<string,string>> $catalogue
     * @param array<string, string>               $fallbacks
     * @param list<string>                        $locales
     * @param list<string>                        $declared
     */
    private function audit(
        array $scanned,
        array $catalogue,
        array $fallbacks = [],
        ?array $locales = null,
        array $declared = [],
    ): JsTranslationReport {
        $scan = new JsTranslationScan(array_combine($scanned, array_map(static fn (string $k): array => ['a.js:1'], $scanned)));

        return new JsTranslationAudit($this->translator($catalogue, $fallbacks))
            ->audit($scan, $locales ?? array_keys($catalogue), $declared);
    }

    /**
     * @param array<string, array<string, string>> $messages  locale => key => translation
     * @param array<string, string>                $fallbacks
     */
    private function translator(array $messages, array $fallbacks = []): TranslatorBagInterface
    {
        $catalogues = [];

        foreach ($messages as $locale => $keys) {
            $catalogues[$locale] = new MessageCatalogue($locale, ['javascript' => $keys]);
        }

        foreach ($fallbacks as $locale => $fallback) {
            $catalogues[$locale]->addFallbackCatalogue($catalogues[$fallback]);
        }

        return $this->bag(array_values($catalogues));
    }

    /**
     * @param list<MessageCatalogueInterface> $catalogues
     */
    private function bag(array $catalogues): TranslatorBagInterface
    {
        return new readonly class($catalogues) implements TranslatorBagInterface, TranslatorInterface {
            /**
             * @param list<MessageCatalogueInterface> $catalogues
             */
            public function __construct(private array $catalogues)
            {
            }

            public function getCatalogue(?string $locale = null): MessageCatalogueInterface
            {
                foreach ($this->catalogues as $catalogue) {
                    if ($catalogue->getLocale() === $locale) {
                        return $catalogue;
                    }
                }

                return new MessageCatalogue($locale ?? 'en');
            }

            public function getCatalogues(): array
            {
                return $this->catalogues;
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id;
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }
}
