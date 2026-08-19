<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Turns on thousands grouping for **every** `NumberType` of the application, so quantities,
 * prices and amounts render as "1 234,56" instead of "1234.56".
 *
 * The decimal separator already comes from the active locale; grouping does not — Symfony
 * leaves the `grouping` option off. `NumberToLocalizedStringTransformer` accepts a grouped
 * value as readily as an ungrouped one on submission, so switching it on is backward
 * compatible for parsing.
 *
 * **Opt-in** (`core.form.number_grouping: true`), because it changes how every numeric field
 * of an application looks — not something a bundle should decide on installation. A single
 * field can still opt out with `'grouping' => false`, for a numeric identifier that must not
 * be grouped.
 */
final class NumberTypeGroupingExtension extends AbstractTypeExtension
{
    /**
     * @return iterable<class-string>
     */
    public static function getExtendedTypes(): iterable
    {
        return [NumberType::class];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('grouping', true);
    }
}
