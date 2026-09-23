<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Logger;

use Jul6Art\CoreBundle\Logger\QueryStringRedactingProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The value of a sensitive query parameter never reaches a log line.
 *
 * In production the `fingers_crossed` handler buffers every record of a request and flushes the
 * whole buffer when ONE error occurs — and the buffered records carry the full URI: in the
 * `RouterListener` message and its `request_uri` context, in the `url` and `referrer` that
 * `WebProcessor` adds to every record, in the message of a `NotFoundHttpException`. One unrelated 500
 * and a working signed link, or what someone searched for, lives in the log as long as the log does.
 */
#[CoversClass(QueryStringRedactingProcessor::class)]
final class QueryStringRedactingProcessorTest extends TestCase
{
    public function testTheValueGoesTheParameterNameStays(): void
    {
        $record = $this->process(new LogRecord(
            new \DateTimeImmutable(),
            'request',
            Level::Info,
            'Matched route "app_search_query".',
            ['request_uri' => 'http://localhost/app/search?q=bijoux%20caches'],
            ['url' => '/api/items?page=1&search=bijoux&itemsPerPage=25', 'referrer' => 'https://x.test/verify-email?id=4&_hash=SECRET-SIG'],
        ));

        self::assertSame('http://localhost/app/search?q=[redacted]', $record->context['request_uri']);
        self::assertSame('/api/items?page=1&search=[redacted]&itemsPerPage=25', $record->extra['url'], 'Les autres paramètres restent lisibles.');
        self::assertSame('https://x.test/verify-email?id=4&_hash=[redacted]', $record->extra['referrer']);
    }

    public function testTheMessageAndNestedArraysAreRedactedToo(): void
    {
        $record = $this->process(new LogRecord(
            new \DateTimeImmutable(),
            'request',
            Level::Error,
            'No route found for "GET http://localhost/reset?token=abc123"',
            ['request' => ['uri' => '/a?q=secret', 'method' => 'GET']],
        ));

        self::assertSame('No route found for "GET http://localhost/reset?token=[redacted]"', $record->message);
        self::assertSame(['uri' => '/a?q=[redacted]', 'method' => 'GET'], $record->context['request']);
    }

    /**
     * A `Throwable`'s message cannot be rewritten: the entry is replaced by its class and the
     * redacted message — the only way to keep the value away from the formatter.
     */
    public function testALoggedExceptionCarryingTheUriIsReplaced(): void
    {
        $record = $this->process(new LogRecord(
            new \DateTimeImmutable(),
            'request',
            Level::Error,
            'Uncaught PHP Exception',
            ['exception' => new \RuntimeException('No route found for "GET /app/search?q=bijoux"')],
        ));

        self::assertSame(
            ['class' => \RuntimeException::class, 'message' => 'No route found for "GET /app/search?q=[redacted]"'],
            $record->context['exception'],
        );
    }

    public function testAnInnocentRecordIsReturnedUntouched(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'app', Level::Info, 'GET /faq?page=2&quality=high', ['e' => new \RuntimeException('plain')], ['url' => '/faq?page=2']);

        self::assertSame($record, $this->process($record), '`faq=`, `quality=` ne sont pas `q=` : le nom doit être entier.');
    }

    public function testTheListIsTheOneConfigured(): void
    {
        $record = new QueryStringRedactingProcessor(['iban'])(new LogRecord(new \DateTimeImmutable(), 'app', Level::Info, '/pay?IBAN=LU28&q=kept'));

        self::assertSame('/pay?IBAN=[redacted]&q=kept', $record->message, 'Insensible à la casse, et seulement les noms listés.');
    }

    private function process(LogRecord $record): LogRecord
    {
        return new QueryStringRedactingProcessor()($record);
    }
}
