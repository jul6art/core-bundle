<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Util;

use Jul6Art\CoreBundle\Util\Strings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Strings::class)]
final class StringsTest extends TestCase
{
    /** @return iterable<string, array{string|null, string|null}> */
    public static function upperProvider(): iterable
    {
        yield 'null passes through' => [null, null];
        yield 'empty string passes through' => ['', ''];
        yield 'plain ascii' => ['dupont', 'DUPONT'];
        yield 'surrounding whitespace is trimmed' => ['  dupont  ', 'DUPONT'];
        yield 'accents are uppercased, not mangled' => ['élodie', 'ÉLODIE'];
        yield 'ligatures and cedillas' => ['françois cœur', 'FRANÇOIS CŒUR'];
        yield 'already uppercase is stable' => ['DUPONT', 'DUPONT'];
    }

    /** @return iterable<string, array{string|null, string|null}> */
    public static function lowerProvider(): iterable
    {
        yield 'null passes through' => [null, null];
        yield 'empty string passes through' => ['', ''];
        yield 'plain ascii' => ['EXAMPLE.COM', 'example.com'];
        yield 'surrounding whitespace is trimmed' => ["\tEXAMPLE.COM\n", 'example.com'];
        yield 'accents are lowercased, not mangled' => ['ÉLODIE', 'élodie'];
    }

    #[DataProvider('upperProvider')]
    public function testUpper(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Strings::upper($input));
    }

    #[DataProvider('lowerProvider')]
    public function testLower(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Strings::lower($input));
    }

    /**
     * The whole point of lowerEmail() over lower(): a soft-deleted UNIQUE column carries
     * the `_DELETED_<timestamp>` marker, and lowercasing it to `_deleted_<timestamp>`
     * would break the restore regex.
     */
    public function testLowerEmailPreservesTheSoftDeleteMarker(): void
    {
        self::assertSame('john@example.com_DELETED_1750000000', Strings::lowerEmail('JOHN@EXAMPLE.COM_DELETED_1750000000'));
    }

    public function testLowerEmailTrimsOnlyTheAddressPartWhenMarked(): void
    {
        self::assertSame('john@example.com_DELETED_42', Strings::lowerEmail('  JOHN@EXAMPLE.COM  _DELETED_42'));
    }

    public function testLowerEmailWithoutMarkerBehavesLikeLower(): void
    {
        self::assertSame('john@example.com', Strings::lowerEmail('  JOHN@EXAMPLE.COM '));
    }

    /** A marker without digits is not a marker — it must be lowercased like any other text. */
    public function testLowerEmailIgnoresAMarkerWithoutTimestamp(): void
    {
        self::assertSame('john@example.com_deleted_', Strings::lowerEmail('JOHN@EXAMPLE.COM_DELETED_'));
    }

    public function testLowerEmailPassesNullAndEmptyThrough(): void
    {
        self::assertNull(Strings::lowerEmail(null));
        self::assertSame('', Strings::lowerEmail(''));
    }

    public function testLowerHostSharesTheEmailSemantics(): void
    {
        self::assertSame('acme.example.com_DELETED_7', Strings::lowerHost('ACME.EXAMPLE.COM_DELETED_7'));
        self::assertSame('acme.example.com', Strings::lowerHost(' ACME.EXAMPLE.COM '));
    }

    /**
     * The marker is published as a constant so the soft-delete trait and the cascade helper
     * cannot drift from their own copy of the literal — and its value leaks into stored data
     * and into DQL, so it is a contract, not an implementation detail. Read through
     * reflection because a direct comparison is a tautology the analyser rejects.
     */
    // ── marqueur de suppression douce ─────────────────────────────────────

    public function testMarkDeletedAppendsTheMarkerAndATimestamp(): void
    {
        $marked = Strings::markDeleted('ada@example.test');

        self::assertMatchesRegularExpression('/^ada@example\.test_DELETED_\d+$/', $marked);
    }

    public function testMarkDeletedIsIdempotent(): void
    {
        $once = Strings::markDeleted('ada@example.test');

        self::assertSame($once, Strings::markDeleted($once));
    }

    public function testRestoreDeletedGivesBackTheOriginalValue(): void
    {
        self::assertSame('ada@example.test', Strings::restoreDeleted('ada@example.test_DELETED_1755000000'));
    }

    public function testRestoreDeletedLeavesAnUnmarkedValueAndNullAlone(): void
    {
        self::assertSame('ada@example.test', Strings::restoreDeleted('ada@example.test'));
        self::assertNull(Strings::restoreDeleted(null));
    }

    /**
     * Seul un marqueur **final** suivi de chiffres est retiré : une valeur qui le contient au
     * milieu n'est pas tronquée, sinon restaurer une adresse la mutilerait.
     */
    public function testRestoreDeletedOnlyStripsATrailingMarker(): void
    {
        self::assertSame(
            'ada_DELETED_42@example.test',
            Strings::restoreDeleted('ada_DELETED_42@example.test'),
        );
    }

    /**
     * L'aller-retour complet, tel que les services l'enchaînent : marquer avant la suppression
     * douce, restaurer au rétablissement.
     */
    public function testTheRoundTripReturnsTheStartingValue(): void
    {
        self::assertSame('SKU-001', Strings::restoreDeleted(Strings::markDeleted('SKU-001')));
    }

    public function testTheSoftDeleteMarkerIsPublished(): void
    {
        self::assertSame('_DELETED_', new \ReflectionClassConstant(Strings::class, 'DELETED_SUFFIX')->getValue());
    }
}
