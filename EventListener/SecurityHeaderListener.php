<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\EventListener;

use Jul6Art\CoreBundle\Security\CspNonce;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Defence-in-depth HTTP response headers, so that an XSS — stored or reflected — does not
 * escalate into a full take-over.
 *
 * Two families, on purpose:
 *
 * - the **hard headers** (nosniff, Referrer-Policy, X-Frame-Options, Permissions-Policy,
 *   HSTS) are set on every response; they rarely break anything;
 * - the **Content-Security-Policy** starts in report-only mode, because a wrong policy breaks
 *   pages silently. Watch the reports, then flip `csp_enforce`. An inline script gets through by
 *   the page's nonce (`{{ csp_nonce() }}`, {@see CspNonce}), added to `script-src` only on a page
 *   that asked for one.
 *
 * Only **missing** headers are filled, so a controller that set its own keeps it — a CMS
 * preview needing `SAMEORIGIN` to survive its own iframe still works.
 *
 * **Disabled by default.** Installing a utility bundle must not change the HTTP responses of
 * an application that did not ask for it: `X-Frame-Options: DENY` alone would break any
 * legitimate embedding. Enable it explicitly:
 *
 * ```yaml
 * core:
 *     security_headers:
 *         enabled: true
 *         csp_enforce: false
 * ```
 */
final readonly class SecurityHeaderListener
{
    /**
     * Headers set unless the application overrode or removed them. `X-Frame-Options: DENY`
     * rather than `SAMEORIGIN`: a page that needs framing says so itself.
     */
    private const array DEFAULT_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'DENY',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ];

    /**
     * A deliberately closed baseline: a library cannot know which hosts an application talks
     * to. `style-src` allows inline styles because template-level `style="…"` attributes are
     * still widespread; `script-src` does not, which is what makes the policy worth having.
     * Widen it through `core.security_headers.csp_policy`.
     */
    private const array DEFAULT_CSP = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "font-src 'self' data:",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
    ];

    /**
     * @param array<string, string|null> $headers overrides merged over the defaults; a null
     *                                            value drops that header entirely
     */
    public function __construct(
        private bool $enabled = false,
        private bool $cspEnforce = false,
        private ?string $cspPolicy = null,
        private array $headers = [],
    ) {
    }

    /**
     * Low priority so it runs *after* the controllers and the other listeners have set their
     * own headers — this one only fills the gaps.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        foreach ([...self::DEFAULT_HEADERS, ...$this->headers] as $name => $value) {
            if (null === $value) {
                continue;
            }

            self::setIfMissing($event->getResponse(), $name, $value);
        }

        $policy = $this->cspPolicy ?? implode('; ', self::DEFAULT_CSP);
        $nonce = CspNonce::of($event->getRequest());

        self::setIfMissing(
            $event->getResponse(),
            $this->cspEnforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
            null === $nonce ? $policy : self::allowNonce($policy, $nonce),
        );
    }

    /**
     * Adds the page's nonce ({@see CspNonce}) to the directives that govern inline scripts.
     *
     * Without `script-src`, the browser reads `default-src`: the nonce goes into a `script-src`
     * copied from it. A directive that already lets every inline script through (`'unsafe-inline'`
     * with no hash and no nonce) is left alone — a nonce would switch `'unsafe-inline'` off and
     * block the very scripts that policy was written for. `style-src` is never touched, for the
     * same reason: its `'unsafe-inline'` is what keeps the `style="…"` attributes working.
     */
    private static function allowNonce(string $policy, string $nonce): string
    {
        $directives = [];
        foreach (explode(';', $policy) as $directive) {
            $tokens = preg_split('/\s+/', trim($directive), -1, \PREG_SPLIT_NO_EMPTY);
            if (\is_array($tokens) && [] !== $tokens) {
                $directives[] = $tokens;
            }
        }

        $names = array_map(static fn (array $tokens): string => strtolower($tokens[0]), $directives);
        $fallback = array_search('default-src', $names, true);

        if (!\in_array('script-src', $names, true) && false !== $fallback) {
            $directives[] = ['script-src', ...\array_slice($directives[$fallback], 1)];
            $names[] = 'script-src';
        }

        foreach ($directives as $i => $tokens) {
            if (\in_array($names[$i], ['script-src', 'script-src-elem'], true) && !self::allowsEveryInlineScript($tokens)) {
                $directives[$i][] = "'nonce-".$nonce."'";
            }
        }

        return implode('; ', array_map(static fn (array $tokens): string => implode(' ', $tokens), $directives));
    }

    /**
     * @param list<string> $tokens a directive, its name first
     */
    private static function allowsEveryInlineScript(array $tokens): bool
    {
        $sources = array_map(strtolower(...), $tokens);

        foreach ($sources as $source) {
            if (str_starts_with($source, "'nonce-") || 1 === preg_match("/^'sha(256|384|512)-/", $source)) {
                return false;
            }
        }

        return \in_array("'unsafe-inline'", $sources, true);
    }

    private static function setIfMissing(Response $response, string $name, string $value): void
    {
        if ($response->headers->has($name)) {
            return;
        }

        $response->headers->set($name, $value);
    }
}
