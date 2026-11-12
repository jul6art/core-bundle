<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Translation;

use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An application's own source, as cegeta's country map: the language of the country a proxy names.
 */
final readonly class CountryLocaleSource implements LocaleSourceInterface
{
    #[\Override]
    public function locale(Request $request, ?UserInterface $user): ?string
    {
        return match ($request->headers->get('CF-IPCountry')) {
            'FR', 'BE' => 'fr',
            'GB' => 'en',
            default => null,
        };
    }
}
