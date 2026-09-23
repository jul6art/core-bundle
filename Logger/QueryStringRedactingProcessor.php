<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Keeps the value of sensitive query parameters out of the log files.
 *
 * ## The leak this closes
 *
 * ⚠️ **The URI of a request is written to the log by the framework itself**: the `RouterListener`
 * message and its `request_uri` context, the message of a `NotFoundHttpException` — and, if the
 * project registers `WebProcessor`, the `url` and `referrer` it adds to every record. In production the `fingers_crossed` handler buffers every
 * record of a request and flushes the whole buffer when ONE error occurs — so one unrelated 500 writes
 * the full query string of that request, and it lives as long as the log does.
 *
 * Two families of values must not survive that, and they are the defaults:
 *
 * - **the secret of a signed link** — `_hash` (e-mail verification), `token` / `_token` (password
 *   reset, CSRF): whoever reads it can use the link;
 * - **what someone searched for** — `q` (the header search), `search` (the lists' bar): the same
 *   keystroke under two names, and "where are my jewels" has no business in a log file.
 *
 * ⚠️ **The value only, never the parameter name.** A reader must still see THAT a link was signed or
 * a search was made, otherwise the log stops explaining what happened.
 *
 * ⚠️ **A `Throwable` is REPLACED** by its class and redacted message — and those of its `previous`
 * chain — when any of them carries such a value: an exception's message cannot be rewritten, and
 * handing the formatter something else is the only way to keep the value away from it. Its stack
 * trace is lost for that one record — the price.
 *
 * Moved here from cegeta (`CredentialRedactingProcessor`, ADR-0037), where it was the only one of
 * three products to do it.
 */
final readonly class QueryStringRedactingProcessor implements ProcessorInterface
{
    /** @var list<string> */
    public const array DEFAULT_PARAMETERS = ['_hash', 'token', '_token', 'q', 'search'];

    /**
     * ⚠️ **Low, so it runs LAST among the logger's processors**: one that copies the URI into the
     * record (`WebProcessor`'s `url` and `referrer`) must run before it, or the copy stays in clear.
     * Handler-level processors still run after it — they are for the project to order.
     */
    public const int PRIORITY = -1024;

    private string $pattern;

    /**
     * @param list<string> $parameters query parameter names whose VALUE is redacted, case-insensitively
     */
    public function __construct(array $parameters = self::DEFAULT_PARAMETERS)
    {
        $names = implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $parameters));

        // `[?&]` before the name, `=` right after: `faq=` and `quality=` are not `q=`.
        $this->pattern = '' === $names ? '/(?!)/' : '/([?&](?:'.$names.')=)[^&#\s"\'<>]+/i';
    }

    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $this->redact($record->message);
        $context = $this->redactAll($record->context);
        $extra = $this->redactAll($record->extra);

        if ($message === $record->message && $context === $record->context && $extra === $record->extra) {
            return $record;
        }

        return $record->with(message: $message, context: $context, extra: $extra);
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private function redactAll(array $values): array
    {
        foreach ($values as $key => $value) {
            if (\is_string($value)) {
                $values[$key] = $this->redact($value);
            } elseif (\is_array($value)) {
                $values[$key] = $this->redactAll($value);
            } elseif ($value instanceof \Throwable) {
                if ($this->carriesAValue($value)) {
                    $values[$key] = $this->describe($value);
                }
            }
        }

        return $values;
    }

    /**
     * Whether the throwable or any exception it wraps carries a redacted value — the formatter
     * serialises the whole `previous` chain, so a neutral outer message is not enough.
     */
    private function carriesAValue(\Throwable $throwable): bool
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($this->redact($current->getMessage()) !== $current->getMessage()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed> the class and redacted message, and the same for `previous`
     */
    private function describe(\Throwable $throwable): array
    {
        $description = ['class' => $throwable::class, 'message' => $this->redact($throwable->getMessage())];

        if (null !== $throwable->getPrevious()) {
            $description['previous'] = $this->describe($throwable->getPrevious());
        }

        return $description;
    }

    private function redact(string $subject): string
    {
        return (string) preg_replace($this->pattern, '$1[redacted]', $subject);
    }
}
