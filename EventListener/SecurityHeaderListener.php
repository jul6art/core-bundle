<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\EventListener;

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
 *   pages silently. Watch the reports, then flip `csp_enforce`.
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

        self::setIfMissing(
            $event->getResponse(),
            $this->cspEnforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
            $this->cspPolicy ?? implode('; ', self::DEFAULT_CSP),
        );
    }

    private static function setIfMissing(Response $response, string $name, string $value): void
    {
        if ($response->headers->has($name)) {
            return;
        }

        $response->headers->set($name, $value);
    }
}
