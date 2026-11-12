<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Twig;

use Jul6Art\CoreBundle\Translation\Locale\SupportedLocales;
use Jul6Art\CoreBundle\Twig\LocaleExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * What the selector shows: the offered list, and an SVG flag per language — never an emoji, which
 * Windows renders as two letters (ADR-0001).
 */
#[CoversClass(LocaleExtension::class)]
final class LocaleExtensionTest extends TestCase
{
    public function testTheSelectorListsTheOfferedLanguagesWithTheirFlags(): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{% for code in backoffice_locales() %}[{{ code }}:{{ locale_flag(code) }}]{% endfor %}']));
        $twig->addExtension(new LocaleExtension(new SupportedLocales(['fr', 'en', 'nl', 'de', 'lb'], 'en')));

        $html = $twig->render('t');

        foreach (['fr', 'en', 'nl', 'de', 'lb'] as $code) {
            self::assertMatchesRegularExpression('#\['.$code.':<svg [^>]*aria-hidden="true"#', $html, $code.' has its SVG flag, unescaped.');
        }
    }

    public function testTheNetherlandsAndLuxembourgAreToldApart(): void
    {
        $extension = new LocaleExtension(new SupportedLocales(['nl', 'lb'], 'nl'));

        self::assertStringContainsString('#21468B', $extension->flag('nl'));
        self::assertStringContainsString('#00A1DE', $extension->flag('lb'));
    }

    public function testALanguageWithoutAFlagRendersNothingRatherThanFailing(): void
    {
        self::assertSame('', new LocaleExtension(new SupportedLocales(['pt'], 'pt'))->flag('pt'));
    }
}
