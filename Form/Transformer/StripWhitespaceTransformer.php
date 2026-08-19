<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Strips the separators an input mask drew, so the stored value matches the column.
 *
 * A mask like `000 000 000 00000` (SIRET) or `aa00 XXXX …` (IBAN) posts the spaces it displayed;
 * `Assert\Length` then rejects the value for being too long. This transformer removes them on
 * submission and leaves the display untouched — the mask redraws itself when the field connects.
 *
 * ```php
 * $builder->add('siret', CustomSiretType::class)
 *     ->get('siret')->addModelTransformer(new StripWhitespaceTransformer(digitsOnly: true));
 * ```
 *
 * @implements DataTransformerInterface<string, string>
 */
final class StripWhitespaceTransformer implements DataTransformerInterface
{
    /**
     * @param bool $digitsOnly strips everything that is not a digit, not just whitespace —
     *                         for a purely numeric column such as a SIRET
     */
    public function __construct(
        private readonly bool $digitsOnly = false,
    ) {
    }

    /**
     * Entity → view: passes through. The view layer re-applies the visual mask on connect, so
     * formatting here would fight it.
     */
    #[\Override]
    public function transform(mixed $value): string
    {
        return (string) ($value ?? '');
    }

    /**
     * View → entity: an emptied field becomes `null` rather than `''`, so a nullable column
     * does not end up storing an empty string that no `Assert\NotBlank` would catch.
     */
    #[\Override]
    public function reverseTransform(mixed $value): ?string
    {
        $string = (string) ($value ?? '');

        if ('' === $string) {
            return null;
        }

        $cleaned = preg_replace($this->digitsOnly ? '/\D+/' : '/\s+/', '', $string);

        return null === $cleaned || '' === $cleaned ? null : $cleaned;
    }
}
