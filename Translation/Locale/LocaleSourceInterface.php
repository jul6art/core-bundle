<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * One place a language can come from: the address, the session, the account, a link, the browser….
 *
 * A source hands back a CANDIDATE, unchecked: {@see LocalePreference} keeps it only if the interface
 * offers it, and asks the next source otherwise — a stale session or a crafted `?lang=` never breaks
 * a page. `null` means "nothing here".
 *
 * The bundle ships six (`route`, `session`, `account`, `query`, `cookie`, `accept_language`); an
 * application adds its own by naming a service of this interface in `core.locale.sources`, at the
 * rank it belongs to.
 */
interface LocaleSourceInterface
{
    public function locale(Request $request, ?UserInterface $user): ?string;
}
