<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Util;

/**
 * String normalization helpers for entity setters that enforce a canonical case
 * (uppercase for proper nouns and postal addresses, lowercase for emails and URLs).
 *
 * All methods are UTF-8-safe (`mb_strtoupper` / `mb_strtolower`) so accented characters
 * are normalized correctly (`é → É`, not `é → é`). They preserve `null` and empty strings
 * as-is so they can be used in setters that accept `?string` without extra guards.
 */
final class Strings
{
    /**
     * Marker appended to a UNIQUE column value when its row is soft-deleted, so the
     * original value becomes available again.
     *
     * Published as a constant because three places need to agree on it: the soft-delete
     * entity trait, the cascade helper's DQL, and {@see self::lowerEmail()} — which has to
     * leave it alone while lowercasing everything around it.
     */
    public const string DELETED_SUFFIX = '_DELETED_';

    /**
     * Trim + uppercase. Returns the input unchanged when null/empty.
     *
     * Used for: first/last names, company names, city, country code.
     */
    public static function upper(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        return mb_strtoupper(trim($value), 'UTF-8');
    }

    /**
     * Trim + lowercase. Returns the input unchanged when null/empty.
     *
     * Used for: domains, URLs (host portion), tags rendered in lowercase pipelines.
     */
    public static function lower(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        return mb_strtolower(trim($value), 'UTF-8');
    }

    /**
     * Alias of {@see self::lowerEmail()} for hostnames — the preserve-marker semantics are
     * identical, just clearer at call sites that handle DNS names rather than addresses.
     */
    public static function lowerHost(?string $value): ?string
    {
        return self::lowerEmail($value);
    }

    /**
     * Trim + lowercase that preserves the {@see self::DELETED_SUFFIX} marker. Without
     * this, lowercasing the whole string would turn `_DELETED_1750000000` into
     * `_deleted_1750000000` and break the restore path, which matches on the uppercase
     * form.
     *
     * Used for the UNIQUE email/hostname columns that soft-delete suffixes.
     */
    public static function lowerEmail(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        if (1 === preg_match(\sprintf('/^(.*?)(%s\d+)$/', preg_quote(self::DELETED_SUFFIX, '/')), $value, $matches)) {
            return mb_strtolower(trim($matches[1]), 'UTF-8').$matches[2];
        }

        return mb_strtolower(trim($value), 'UTF-8');
    }
}
