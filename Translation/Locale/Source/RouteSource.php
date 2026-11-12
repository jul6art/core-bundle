<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale\Source;

use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The `_locale` of the matched route: `/fr/…` and `/en/…` are two documents, and a page is in the
 * language its address says — not a preference, the page itself. First in the chain wherever routes
 * carry it; a no-op where they don't.
 */
final readonly class RouteSource implements LocaleSourceInterface
{
    #[\Override]
    public function locale(Request $request, ?UserInterface $user): ?string
    {
        $locale = $request->attributes->get('_locale');

        return \is_string($locale) ? $locale : null;
    }
}
