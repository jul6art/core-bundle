<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Twig;

use Jul6Art\CoreBundle\Security\CspNonce;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `csp_nonce()` — the nonce of the page, for an inline script the policy must let through:
 *
 * ```twig
 * <script nonce="{{ csp_nonce() }}">window.jwtToken = {{ jwt_token()|json_encode|raw }};</script>
 * ```
 *
 * Registered whether `security_headers` is on or not: a shared template (a bundle's shell) writes
 * the attribute without knowing, and without a policy the browser simply ignores it.
 */
final class CspExtension extends AbstractExtension
{
    public function __construct(private readonly CspNonce $nonce)
    {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->nonce->get(...)),
        ];
    }
}
