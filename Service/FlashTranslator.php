<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves a flash message's translation domain from its key prefix.
 *
 * This is what lets a controller write `addSuccessFlash('user.created')` without naming a
 * domain, and without every flash of the application ending up in `messages` — the file that
 * becomes a dumping ground when nothing decides otherwise.
 *
 * ```yaml
 * core:
 *     flash:
 *         default_domain: 'messages'
 *         domain_map:
 *             'organization.domain.': 'domain'   # declare the longer prefix first
 *             'organization.': 'organization'
 *             'user.': 'user'
 * ```
 *
 * An application that maps nothing gets plain Symfony behaviour: everything in the default
 * domain.
 *
 * The prefix map is one of two conventions in use. The other one names the domain **per
 * controller** rather than per key — see {@see \Jul6Art\CoreBundle\Controller\AbstractController::translationDomain()},
 * whose value arrives here as `$domain` and short-circuits the map entirely.
 */
final readonly class FlashTranslator
{
    /**
     * @param array<string, string> $domainMap key prefix → translation domain. **Order matters**:
     *                                         the first matching prefix wins, so a longer prefix
     *                                         must come before the shorter one it starts with
     */
    public function __construct(
        private TranslatorInterface $translator,
        private array $domainMap = [],
        private string $defaultDomain = 'messages',
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @param string|null          $domain     forces the domain, bypassing the prefix map; this is
     *                                         how a controller declaring a `translationDomain()` keeps
     *                                         working with keys that carry no domain prefix
     */
    public function trans(string $key, array $parameters = [], ?string $domain = null): string
    {
        if (null !== $domain) {
            return $this->translator->trans($key, $parameters, $domain);
        }

        foreach ($this->domainMap as $prefix => $domain) {
            if (str_starts_with($key, $prefix)) {
                return $this->translator->trans($key, $parameters, $domain);
            }
        }

        return $this->translator->trans($key, $parameters, $this->defaultDomain);
    }
}
