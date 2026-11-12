<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale\Source;

use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A language carried by a link (`?lang=nl` on a printed QR code, a campaign, a share).
 *
 * ⚠️ Read from the raw parameters, never with `InputBag::get()`: `?lang[]=de` would raise a
 * `BadRequestException` — a 400 on any page, triggered by a link — before the value could be ignored.
 * Anything but a string is no candidate.
 *
 * ⚠️ Not `_locale` where routes carry it: Symfony reserves the name, and `enabled_locales` makes it a
 * route requirement. The skeleton's web mode uses `?_locale=` on routes without one, which is fine.
 */
final readonly class QuerySource implements LocaleSourceInterface
{
    public function __construct(
        private string $parameter,
    ) {
    }

    #[\Override]
    public function locale(Request $request, ?UserInterface $user): ?string
    {
        $locale = $request->query->all()[$this->parameter] ?? null;

        return \is_string($locale) ? $locale : null;
    }
}
