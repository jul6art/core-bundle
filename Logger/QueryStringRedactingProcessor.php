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
 * message and its `request_uri` context, the `url` and `referrer` `WebProcessor` adds to every record,
 * the message of a `NotFoundHttpException`. In production the `fingers_crossed` handler buffers every
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
 * ⚠️ **A `Throwable` is REPLACED** by its class and redacted message when its message carries such a
 * value: an exception's message cannot be rewritten, and handing the formatter something else is the
 * only way to keep the value away from it. Its stack trace is lost for that one record — the price.
 *
 * Moved here from cegeta (`CredentialRedactingProcessor`, ADR-0037), where it was the only one of
 * three products to do it.
 */
final readonly class QueryStringRedactingProcessor implements ProcessorInterface
{
    /** @var list<string> */
    public const array DEFAULT_PARAMETERS = ['_hash', 'token', '_token', 'q', 'search'];

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
                $redacted = $this->redact($value->getMessage());

                if ($redacted !== $value->getMessage()) {
                    $values[$key] = ['class' => $value::class, 'message' => $redacted];
                }
            }
        }

        return $values;
    }

    private function redact(string $subject): string
    {
        return (string) preg_replace($this->pattern, '$1[redacted]', $subject);
    }
}
