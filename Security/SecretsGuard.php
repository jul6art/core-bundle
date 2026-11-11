<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Security;

/**
 * Which production secrets are still a default: empty, a placeholder of a recipe, or the very value
 * the repository commits in its `.env`.
 *
 * ⚠️ A secret that is committed is no secret: anyone who reads the repository signs the session
 * cookies (`APP_SECRET`), publishes on the Mercure hub (`MERCURE_JWT_SECRET`) or opens the JWT
 * private key (`JWT_PASSPHRASE`). Those values are fine on a workstation, and only there — so the
 * check runs when a production cache is built ({@see SecretsGuardWarmer}), and refuses to build it.
 *
 * It answers with the variables' NAMES, never their values: the message ends up in a deployment log.
 */
final readonly class SecretsGuard
{
    /** Shorter than this, `APP_SECRET` is guessable: Symfony's own recipes generate 32 characters. */
    public const int APP_SECRET_MIN_LENGTH = 32;

    /** The placeholders the recipes and the skeletons write. */
    public const array PLACEHOLDERS = [
        'APP_SECRET' => ['ChangeMe', '!ChangeMe!', 'change_me', 'change_me_to_a_random_32_char_string'],
        'MERCURE_JWT_SECRET' => ['!ChangeThisMercureHubJWTSecretKey!'],
        'JWT_PASSPHRASE' => ['change_me', 'change_me_jwt_passphrase', '!ChangeMe!'],
    ];

    /**
     * @param list<string> $declared the application's own secrets: refused when empty or committed
     * @param bool         $jwt      whether LexikJWTAuthenticationBundle is installed, so JWT_PASSPHRASE matters
     */
    public function __construct(
        private array $declared = [],
        private bool $jwt = false,
    ) {
    }

    /**
     * The variables the guard reads.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique(array_merge(['APP_SECRET', 'MERCURE_JWT_SECRET', 'JWT_PASSPHRASE'], $this->declared)));
    }

    /**
     * @param array<string, string|null> $environment the values the application runs with, null when undefined
     * @param array<string, string>      $committed   the values of the committed `.env`
     *
     * @return list<string> the names of the variables that are still a default, in a stable order
     */
    public function violations(array $environment, array $committed): array
    {
        $violations = [];

        $secret = $environment['APP_SECRET'] ?? null;
        if (null === $secret
            || \strlen($secret) < self::APP_SECRET_MIN_LENGTH
            || self::isPlaceholder('APP_SECRET', $secret)
            || self::isCommitted('APP_SECRET', $secret, $committed)) {
            $violations[] = 'APP_SECRET';
        }

        // Only when the application talks to a hub: a variable it never defines is no default.
        $mercure = $environment['MERCURE_JWT_SECRET'] ?? null;
        if (null !== $mercure && self::isPlaceholder('MERCURE_JWT_SECRET', $mercure)) {
            $violations[] = 'MERCURE_JWT_SECRET';
        }

        if ($this->jwt) {
            $passphrase = $environment['JWT_PASSPHRASE'] ?? null;
            if (null === $passphrase || '' === $passphrase
                || self::isPlaceholder('JWT_PASSPHRASE', $passphrase)
                || self::isCommitted('JWT_PASSPHRASE', $passphrase, $committed)) {
                $violations[] = 'JWT_PASSPHRASE';
            }
        }

        foreach ($this->declared as $name) {
            $value = $environment[$name] ?? null;
            if (null === $value || '' === $value || (isset($committed[$name]) && $committed[$name] === $value)) {
                $violations[] = $name;
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * The variables of a `.env` file, as written: `NAME=value`, `export NAME=value`, a value quoted
     * or not, a comment after an unquoted value.
     *
     * ⚠️ Not a full dotenv parser: `${OTHER}` stays as written. A secret is a literal — a committed
     * secret that is built from another variable is caught through that other one.
     *
     * @return array<string, string>
     */
    public static function parseDotenv(string $content): array
    {
        $variables = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (1 !== preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $match)) {
                continue;
            }
            $value = trim($match[2]);
            if (1 === preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $value, $quoted)) {
                $value = stripcslashes($quoted[1]);
            } elseif (1 === preg_match("/^'([^']*)'/", $value, $quoted)) {
                $value = $quoted[1];
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }
            $variables[$match[1]] = $value;
        }

        return $variables;
    }

    private static function isPlaceholder(string $name, string $value): bool
    {
        return \in_array($value, self::PLACEHOLDERS[$name] ?? [], true);
    }

    /**
     * @param array<string, string> $committed
     */
    private static function isCommitted(string $name, string $value, array $committed): bool
    {
        return isset($committed[$name]) && '' !== $committed[$name] && $committed[$name] === $value;
    }
}
