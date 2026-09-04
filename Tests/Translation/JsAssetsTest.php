<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation;

use Jul6Art\CoreBundle\Translation\JsTranslationScanner;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The JavaScript half of the socle, guarded from PHP.
 *
 * ⚠️ There is no JavaScript test runner in this ecosystem's bundles, so this file cannot assert
 * on `t()` at runtime — it asserts on the contract other packages depend on, and on what the
 * scanner makes of the socle's own sources. Standing up vitest here is worth doing; it is a lot
 * more than this lot, and pretending otherwise would have meant shipping the socle untested.
 */
#[CoversNothing]
final class JsAssetsTest extends TestCase
{
    /**
     * ⚠️ Sixteen controllers across `superp` and `datatable-bundle` spread `translatableValues`
     * into their `static values`. Renaming or dropping it breaks their *import*, inside a bundle,
     * with a message no one can trace back to here.
     */
    public function testTheMixinKeepsItsPublicContract(): void
    {
        $mixin = self::read('mixins/translatable.js');

        self::assertStringContainsString('export const translatableValues', $mixin);
        self::assertStringContainsString('export function useTranslatable', $mixin);
    }

    public function testTheRegistryKeepsItsPublicContract(): void
    {
        $registry = self::read('i18n/registry.js');

        foreach (['registerTranslator', 'hasTranslator', 'resetTranslator', 't'] as $exported) {
            self::assertStringContainsString(\sprintf('export function %s(', $exported), $registry);
        }
    }

    /**
     * The order is the whole migration strategy: while a template still ships its
     * `data-…-translations-value`, that tree answers; the day it goes, the catalogue does. Read
     * the other way round, a project whose keys have not moved to the `javascript` domain yet
     * would show raw keys the moment the socle is installed.
     */
    public function testTheMixinAsksTheLocalTreeBeforeTheRegistry(): void
    {
        $mixin = self::read('mixins/translatable.js');

        $local = strpos($mixin, 'const local = resolve(');
        $registry = strpos($mixin, 'translate(key, parameters)');

        self::assertIsInt($local);
        self::assertIsInt($registry);
        self::assertLessThan($registry, $local, 'The transitional tree must be consulted first.');
    }

    /**
     * The socle defines `t()`; it never reads a key of its own. Anything found here would be a
     * key no catalogue is asked to hold — and, since these two files are the scanner's own
     * grammar, a scanner bug at the same time.
     */
    public function testTheSocleReadsNoKeyOfItsOwn(): void
    {
        $scan = new JsTranslationScanner()->scan(\dirname(__DIR__, 2).'/assets');

        self::assertSame([], $scan->keys());
        self::assertSame([], $scan->prefixes());
        self::assertSame([], $scan->dynamicCalls(), 'A declaration is not a call; see JsTranslationScanner::isDeclaration().');
    }

    private static function read(string $path): string
    {
        $full = \dirname(__DIR__, 2).'/assets/'.$path;

        self::assertFileExists($full);

        return (string) file_get_contents($full);
    }
}
