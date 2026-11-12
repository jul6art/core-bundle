<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Test;

/**
 * What a production configuration must say about HTTP, as rules over the raw `framework` and
 * `core` configurations ({@see KernelConfiguration}). Each violation is a sentence that says what
 * is wrong and what it opens.
 *
 * A setting written as `%env(…)%` passes: the rule checks that it is written, the server check
 * checks the value it receives.
 */
final class HttpHardeningRules
{
    /**
     * @param array<string, mixed> $framework
     * @param array<string, mixed> $core
     *
     * @return list<string>
     */
    public static function violations(array $framework, array $core): array
    {
        $violations = [];

        if (\in_array($framework['trusted_hosts'] ?? null, [null, '', []], true)) {
            $violations[] = 'framework.trusted_hosts is empty: any Host header is accepted, and the absolute URLs built from it (a password reset link) can point to another site.';
        }

        $proxies = $framework['trusted_proxies'] ?? [];
        $proxies = \is_string($proxies) ? explode(',', $proxies) : (\is_array($proxies) ? $proxies : []);
        foreach ($proxies as $proxy) {
            if (\is_string($proxy) && 'REMOTE_ADDR' === strtoupper(trim($proxy))) {
                $violations[] = 'framework.trusted_proxies trusts REMOTE_ADDR: whoever connects counts as a proxy, so an X-Forwarded-For header chooses the client address the limiters and logs see. Keep it under when@dev.';
            }
        }

        if (true !== self::enabled($core, 'security_headers')) {
            $violations[] = 'core.security_headers.enabled is off: no Content-Security-Policy, no HSTS, no nosniff.';
        }

        if (true !== self::enabled($core, 'secrets_guard')) {
            $violations[] = 'core.secrets_guard.enabled is off: a production cache builds with an empty or example APP_SECRET.';
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function enabled(array $config, string $node): mixed
    {
        $section = $config[$node] ?? null;

        return \is_array($section) ? ($section['enabled'] ?? null) : null;
    }
}
