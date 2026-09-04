<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What `CoreExtension::prepend()` produces, measured by running the dump it configures.
 *
 * The convention is one line of YAML and it is the whole point of the socle: a project that
 * installs `symfony/ux-translator` exposes the `javascript` domain, and only that one. Left to
 * the package's own default, every domain of the catalogue is dumped into the browser bundle —
 * 5 955 keys in `superp`, in every locale it serves.
 *
 * ⚠️ These tests assert on the FILE, not on the container. A service argument can be right while
 * the dump is empty: the UX warmer reads `TranslatorBagInterface::getCatalogues()`, which returns
 * the catalogues already loaded, so the order of the warm-ups decides what comes out. Only
 * running both proves the chain.
 */
#[CoversNothing]
final class UxTranslatorPrependTest extends AbstractFunctionalTestCase
{
    public function testOnlyTheJavascriptDomainReachesTheBrowser(): void
    {
        $dump = $this->dump($this->boot('test', withUxTranslator: true));

        self::assertStringContainsString('datatable.filters', $dump);
        self::assertStringContainsString('"javascript"', $dump);

        // The witness: a key of `messages`, the domain a back-office fills with everything.
        self::assertStringNotContainsString('page.title', $dump);
    }

    public function testEveryEnabledLocaleIsDumped(): void
    {
        $dump = $this->dump($this->boot('test', withUxTranslator: true));

        self::assertStringContainsString('Filtres', $dump);
        self::assertStringContainsString('Filters', $dump);
    }

    /**
     * The socle proposes, the application disposes. `prependExtensionConfig()` puts the socle's
     * values *first*, and merging lets the later ones win — so a project that means to expose a
     * second domain just says so in `config/packages/ux_translator.yaml`.
     */
    public function testTheApplicationConfigurationWins(): void
    {
        $dump = $this->dump($this->boot(
            'test',
            withUxTranslator: true,
            uxTranslatorConfig: ['domains' => ['javascript', 'legacy']],
        ));

        self::assertStringContainsString('datatable.filters', $dump);
        self::assertStringContainsString('legacy.label', $dump);
    }

    public function testTheDomainIsConfigurable(): void
    {
        $dump = $this->dump($this->boot('test', ['js_translations' => ['domain' => 'front']], withUxTranslator: true));

        self::assertStringContainsString('front.label', $dump);
        self::assertStringNotContainsString('datatable.filters', $dump);
    }

    /**
     * Opting out gives the package's own behaviour back: every domain, including the one a
     * back-office uses as a dumping ground.
     */
    public function testDisablingTheFeatureRestoresThePackageDefault(): void
    {
        $dump = $this->dump($this->boot('test', ['js_translations' => ['enabled' => false]], withUxTranslator: true));

        self::assertStringContainsString('page.title', $dump);
    }

    /**
     * ⚠️ TypeScript types are dumped alongside the translations on every cache warm-up, and a
     * warm-up runs on every deploy. Nothing reads the `.d.ts` in production.
     */
    public function testTypeScriptTypesAreNotDumpedInProduction(): void
    {
        self::assertFileDoesNotExist($this->dumpDirectory('prod').'/index.d.ts');
        self::assertFileExists($this->dumpDirectory('test').'/index.d.ts');
    }

    /**
     * ⚠️ Prepending configuration for an extension the kernel does not have blows the container
     * up at boot. Most applications of this ecosystem never install `symfony/ux-translator`, and
     * installing `core-bundle` must not break them.
     */
    public function testNothingIsPrependedWithoutTheUxTranslatorBundle(): void
    {
        $container = $this->boot('test');

        self::assertFalse($container->has('ux.translator.cache_warmer.translations_cache_warmer'));
    }

    /**
     * Runs the two warm-ups, in the order a real cache warm-up runs them, and returns the file.
     */
    private function dump(ContainerInterface $container): string
    {
        $directory = $this->warm($container);
        $index = $directory.'/index.js';

        self::assertFileExists($index);

        return (string) file_get_contents($index);
    }

    private function dumpDirectory(string $environment): string
    {
        return $this->warm($this->boot($environment, withUxTranslator: true));
    }

    /**
     * ⚠️ The translator's own warm-up comes FIRST. The UX warmer dumps
     * `TranslatorBagInterface::getCatalogues()`, which returns what is already loaded — call it
     * on a cold translator and the dump is an empty object, with no error anywhere.
     */
    private function warm(ContainerInterface $container): string
    {
        $translator = $container->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        $cacheDir = $container->getParameter('kernel.cache_dir');
        self::assertIsString($cacheDir);

        // ⚠️ WarmableInterface, not CacheWarmerInterface: the FrameworkBundle translator implements
        // the former. Testing for the latter compiles, never matches, and leaves the UX warmer to
        // dump an EMPTY object — `export const messages = {};` — with nothing anywhere saying so.
        self::assertInstanceOf(WarmableInterface::class, $translator);
        $translator->warmUp($cacheDir);

        $warmer = $container->get('ux.translator.cache_warmer.translations_cache_warmer');
        self::assertInstanceOf(CacheWarmerInterface::class, $warmer);
        $warmer->warmUp($cacheDir);

        $directory = $container->getParameter('core.js_translations.dump_directory');
        self::assertIsString($directory);

        return $directory;
    }
}
