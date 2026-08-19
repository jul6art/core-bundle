<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Form\Transformer;

use Jul6Art\CoreBundle\Form\Transformer\StripWhitespaceTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reconciles an input mask with a fixed-length column: the mask posts the separators it drew
 * ("000 000 000 00000" for a SIRET), and `Assert\Length` then rejects the value for being too
 * long. This strips them on the way in, and leaves the display alone on the way out — the mask
 * redraws itself on connect.
 */
#[CoversClass(StripWhitespaceTransformer::class)]
final class StripWhitespaceTransformerTest extends TestCase
{
    /** @return iterable<string, array{string|null, string}> */
    public static function displayProvider(): iterable
    {
        yield 'string passes through with its spaces' => ['123 456 789', '123 456 789'];
        yield 'null becomes an empty field' => [null, ''];
        yield 'empty stays empty' => ['', ''];
    }

    #[DataProvider('displayProvider')]
    public function testTheDisplayedValueIsLeftUntouched(?string $stored, string $expected): void
    {
        self::assertSame($expected, new StripWhitespaceTransformer()->transform($stored));
    }

    public function testWhitespaceIsStrippedOnSubmission(): void
    {
        self::assertSame('12345678901234', new StripWhitespaceTransformer()->reverseTransform('123 456 789 01234'));
    }

    public function testEveryKindOfWhitespaceGoes(): void
    {
        self::assertSame('abcd', new StripWhitespaceTransformer()->reverseTransform("a b\tc\nd"));
    }

    /** IBAN keeps its letters; SIRET does not. */
    public function testDigitsOnlyModeDropsEverythingElse(): void
    {
        self::assertSame('123456', new StripWhitespaceTransformer(digitsOnly: true)->reverseTransform('LU12-34 56'));
        // Hors digitsOnly seuls les espaces sautent : les lettres et les tirets restent.
        self::assertSame('LU12-3456', new StripWhitespaceTransformer()->reverseTransform('LU12-34 56 '));
    }

    /**
     * An emptied field must reach the entity as null, not as '': a nullable column would
     * otherwise store an empty string that no `Assert\NotBlank` catches.
     *
     * @return iterable<string, array{string|null}>
     */
    public static function emptyProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'only spaces' => ['   '];
    }

    #[DataProvider('emptyProvider')]
    public function testAnEmptiedFieldBecomesNull(?string $submitted): void
    {
        self::assertNull(new StripWhitespaceTransformer()->reverseTransform($submitted));
    }

    public function testAValueWithNoDigitsBecomesNullInDigitsOnlyMode(): void
    {
        self::assertNull(new StripWhitespaceTransformer(digitsOnly: true)->reverseTransform('LU--'));
    }

    public function testTheRoundTripSurvivesTheMask(): void
    {
        $transformer = new StripWhitespaceTransformer();

        self::assertSame('12345678901234', $transformer->reverseTransform($transformer->transform('12345678901234')));
    }
}
