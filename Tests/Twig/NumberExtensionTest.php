<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Twig;

use Jul6Art\CoreBundle\Service\NumberFormatter;
use Jul6Art\CoreBundle\Twig\NumberExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Rendered through a real Twig environment: a filter that exists but is not registered under
 * the name templates use is worse than no filter at all.
 */
#[CoversClass(NumberExtension::class)]
final class NumberExtensionTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    public function testTheNumberFilterFormatsLikeTheService(): void
    {
        self::assertSame('1'.self::NBSP.'234,56', $this->render('{{ v|fr_number }}', ['v' => 1234.56]));
    }

    public function testTheNumberFilterTakesADecimalCount(): void
    {
        self::assertSame('1'.self::NBSP.'235', $this->render('{{ v|fr_number(0) }}', ['v' => 1234.56]));
    }

    public function testTheMoneyFilterNeedsItsCurrency(): void
    {
        self::assertSame('1'.self::NBSP.'234,56 CHF', $this->render("{{ v|fr_money('CHF') }}", ['v' => 1234.56]));
    }

    public function testThePercentFilterDefaultsToNoDecimal(): void
    {
        self::assertSame('20'.self::NBSP.'%', $this->render('{{ v|fr_percent }}', ['v' => 20]));
    }

    /**
     * The `fr_*` names are used across hundreds of templates and must keep working; the neutral
     * aliases are what the documentation recommends for a new project.
     */
    public function testEachFilterHasALocaleNeutralAlias(): void
    {
        self::assertSame(
            $this->render('{{ v|fr_number }}', ['v' => 1234.56]),
            $this->render('{{ v|format_number }}', ['v' => 1234.56])
        );
        self::assertSame(
            $this->render("{{ v|fr_money('EUR') }}", ['v' => 1234.56]),
            $this->render("{{ v|format_money('EUR') }}", ['v' => 1234.56])
        );
        self::assertSame(
            $this->render('{{ v|fr_percent }}', ['v' => 20]),
            $this->render('{{ v|format_percent }}', ['v' => 20])
        );
    }

    public function testAnEmptyValueRendersAsNothingSoTheTemplateCanFallBack(): void
    {
        self::assertSame('—', $this->render('{{ v|fr_number ?: "—" }}', ['v' => null]));
    }

    /** @param array<string, mixed> $context */
    private function render(string $template, array $context): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]));
        $twig->addExtension(new NumberExtension(new NumberFormatter()));

        return $twig->render('t', $context);
    }
}
