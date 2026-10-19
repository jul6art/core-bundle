<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The nonce that lets an inline `<script>` through the Content-Security-Policy: the anti-FOUC of
 * a shell, the `window.jwtToken` a layout hands to the API client.
 *
 * Drawn the first time a template asks for it (`{{ csp_nonce() }}`), once per page, and kept on
 * the MAIN request, where {@see \Jul6Art\CoreBundle\EventListener\SecurityHeaderListener} reads it
 * and adds `'nonce-…'` to `script-src`. A page that never asks sends its policy unchanged — which
 * keeps a publicly cached page free of a nonce every visitor would share.
 *
 * A script whose text never changes can be cited by its hash in `csp_policy` instead. A script
 * that carries per-user data (a token, a slug) has no stable hash: only a nonce lets it through.
 */
final readonly class CspNonce
{
    /** Where the nonce waits between the template that drew it and the listener that sends it. */
    public const string ATTRIBUTE = '_csp_nonce';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The nonce of the current page. Outside an HTTP request (an e-mail rendered by a worker)
     * there is no policy to satisfy, and a fresh value is as good as any.
     */
    public function get(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return self::draw();
        }

        $nonce = self::of($request);
        if (null === $nonce) {
            $nonce = self::draw();
            $request->attributes->set(self::ATTRIBUTE, $nonce);
        }

        return $nonce;
    }

    /** The nonce a template drew for this request, or null when none asked for one. */
    public static function of(Request $request): ?string
    {
        $nonce = $request->attributes->get(self::ATTRIBUTE);

        return \is_string($nonce) ? $nonce : null;
    }

    /** 18 random bytes: 24 base64 characters, no padding. */
    private static function draw(): string
    {
        return base64_encode(random_bytes(18));
    }
}
