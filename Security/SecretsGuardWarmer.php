<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Security;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Refuses to build a PRODUCTION cache while a secret is still a default ({@see SecretsGuard}).
 *
 * A cache warmer rather than a listener: `cache:clear` and `cache:warmup` run it on every
 * deployment, BEFORE the new code serves anyone — the deployment fails, the previous release keeps
 * running, and the log names the variables to define. A check at request time would have let the
 * site serve with a known key until the first request said so.
 *
 * Not optional: an optional warmer is skipped by `cache:warmup` unless asked for.
 */
final readonly class SecretsGuardWarmer implements CacheWarmerInterface
{
    /**
     * @param string|null $dotenvPath the committed `.env`, whose values are no secret
     */
    public function __construct(
        private SecretsGuard $guard,
        private string $environment,
        private ?string $dotenvPath,
    ) {
    }

    #[\Override]
    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ('prod' !== $this->environment) {
            return [];
        }

        $violations = $this->guard->violations(self::environment($this->guard->names()), $this->committed());
        if ([] !== $violations) {
            throw new \RuntimeException(\sprintf('These production secrets are empty, a placeholder or the value committed in .env: %s. Define them in .env.local or in the server environment.', implode(', ', $violations)));
        }

        return [];
    }

    /**
     * What the application runs with: Dotenv fills `$_SERVER` and `$_ENV` without overriding a real
     * variable of the process, which `getenv()` still sees when nothing copied it.
     *
     * @param list<string> $names
     *
     * @return array<string, string|null>
     */
    private static function environment(array $names): array
    {
        $environment = [];
        foreach ($names as $name) {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
            $environment[$name] = \is_scalar($value) && false !== $value ? (string) $value : null;
        }

        return $environment;
    }

    /**
     * @return array<string, string>
     */
    private function committed(): array
    {
        if (null === $this->dotenvPath || !is_file($this->dotenvPath)) {
            return [];
        }

        return SecretsGuard::parseDotenv((string) file_get_contents($this->dotenvPath));
    }
}
