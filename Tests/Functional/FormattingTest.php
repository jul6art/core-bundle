<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Jul6Art\CoreBundle\Form\Extension\NumberTypeGroupingExtension;
use Jul6Art\CoreBundle\Service\NumberFormatter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Environment;

/**
 * Wiring of the formatting bricks. The classes themselves are unit-tested; what only a real
 * container can show is that the Twig filters actually reach a template and that the form
 * extension stays out until it is asked for.
 */
#[CoversNothing]
final class FormattingTest extends AbstractFunctionalTestCase
{
    private const string NBSP = "\u{00A0}";

    public function testTheFilterIsUsableFromATemplate(): void
    {
        self::assertSame('1'.self::NBSP.'234,56', $this->render('{{ 1234.56|format_number }}'));
    }

    /** The historical names must keep working: hundreds of templates call them. */
    public function testTheHistoricalFilterNamesAreStillRegistered(): void
    {
        self::assertSame('1'.self::NBSP.'234,56 EUR', $this->render("{{ 1234.56|fr_money('EUR') }}"));
        self::assertSame('20'.self::NBSP.'%', $this->render('{{ 20|fr_percent }}'));
    }

    public function testThePdfHelpersAreUsableFromATemplate(): void
    {
        self::assertStringEndsWith('/public/img/logo.png', $this->render("{{ pdf_image_path('img/logo.png') }}"));
    }

    public function testTheSeparatorsFollowTheConfiguration(): void
    {
        $rendered = $this->render(
            '{{ 1234.56|format_number }}',
            ['number_format' => ['decimal_separator' => '.', 'thousands_separator' => ',']]
        );

        self::assertSame('1,234.56', $rendered);
    }

    public function testThePublicDirectoryFollowsTheConfiguration(): void
    {
        $rendered = $this->render("{{ pdf_image_path('logo.png') }}", ['pdf' => ['public_dir' => '/srv/assets']]);

        self::assertSame('/srv/assets/logo.png', $rendered);
    }

    /** The formatter is available as a service too, for anything outside a template. */
    public function testTheFormatterIsInjectable(): void
    {
        $formatter = $this->boot()->get(NumberFormatter::class);

        self::assertInstanceOf(NumberFormatter::class, $formatter);
        self::assertSame('1'.self::NBSP.'234,56', $formatter->format(1234.56));
    }

    // ── extension de formulaire, opt-in ───────────────────────────────────

    public function testTheFormExtensionStaysOutUntilItIsAskedFor(): void
    {
        self::assertFalse($this->boot()->has(NumberTypeGroupingExtension::class));
    }

    public function testEnablingItRegistersIt(): void
    {
        self::assertTrue($this->boot('test', ['form' => ['number_grouping' => true]])->has(NumberTypeGroupingExtension::class));
    }

    /** @param array<string, mixed> $coreConfig */
    private function render(string $template, array $coreConfig = []): string
    {
        $twig = $this->boot('test', $coreConfig)->get('twig');

        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate($template)->render();
    }
}
