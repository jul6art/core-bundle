<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\EventSubscriber;

use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;
use Jul6Art\CoreBundle\Performance\Store\PerformanceRecord;
use Jul6Art\CoreBundle\Performance\Store\PerformanceStoreInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PerformanceSubscriber implements EventSubscriberInterface
{
    private ?PerformanceRecord $lastRecord = null;

    public function __construct(
        private readonly QueryTracker $tracker,
        private readonly PerformanceStoreInterface $store,
        private readonly bool $enabled,
        /**
         * Routes starting with this prefix are not recorded: the profiler's own dashboard would
         * otherwise measure itself, and every visit to it would add a record to the store it is
         * displaying. Configurable because the prefix belongs to whoever declares the routes —
         * this bundle ships no UI.
         */
        private readonly string $ignoredRoutePrefix = 'admin_performance_',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            // Must run BEFORE Symfony's ProfilerListener (priority -100 on kernel.response),
            // otherwise the DataCollector::collect() call triggered by the profiler reads
            // a null record.
            KernelEvents::RESPONSE => ['onKernelResponse', -50],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Always reset so the always-wired middleware cannot accumulate across requests.
        $this->tracker->reset();
        $this->lastRecord = null;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->isOwnRoute($request)) {
            return;
        }

        $response = $event->getResponse();
        $record = $this->buildRecord($request, $response);

        $this->lastRecord = $record;
        $this->store->append($record);
    }

    public function getLastRecord(): ?PerformanceRecord
    {
        return $this->lastRecord;
    }

    private function isOwnRoute(Request $request): bool
    {
        if ('' === $this->ignoredRoutePrefix) {
            return false;
        }

        $route = (string) $request->attributes->get('_route', '');

        return str_starts_with($route, $this->ignoredRoutePrefix);
    }

    private function buildRecord(Request $request, Response $response): PerformanceRecord
    {
        $startTime = $request->server->get('REQUEST_TIME_FLOAT');
        $durationMs = is_numeric($startTime) ? (microtime(true) - (float) $startTime) * 1000 : 0.0;

        $aggregates = $this->tracker->getAggregates();
        $queries = [];
        foreach ($aggregates as $entry) {
            $queries[] = [
                'sql' => $entry['sql'],
                'count' => $entry['count'],
                'total_time' => $entry['total_time'] * 1000,
            ];
        }

        $content = $response->getContent();
        $responseSize = false === $content ? 0 : \strlen($content);

        return new PerformanceRecord(
            id: bin2hex(random_bytes(8)),
            timestamp: microtime(true),
            route: $request->attributes->get('_route'),
            method: $request->getMethod(),
            uri: $request->getRequestUri(),
            statusCode: $response->getStatusCode(),
            durationMs: $durationMs,
            memoryPeakBytes: memory_get_peak_usage(true),
            queryCount: $this->tracker->getQueryCount(),
            distinctQueryCount: $this->tracker->getDistinctCount(),
            dbTimeMs: $this->tracker->getTotalTime() * 1000,
            responseSizeBytes: $responseSize,
            queries: $queries,
        );
    }
}
