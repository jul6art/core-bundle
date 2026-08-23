<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler;

use Jul6Art\CoreBundle\Performance\EventSubscriber\PerformanceSubscriber;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PerformanceDataCollector extends AbstractDataCollector
{
    public function __construct(private readonly PerformanceSubscriber $subscriber)
    {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $record = $this->subscriber->getLastRecord();

        if (null === $record) {
            $this->data = [
                'active' => false,
                'record' => null,
            ];

            return;
        }

        $this->data = [
            'active' => true,
            'record' => $record->toArray(),
        ];
    }

    public function getName(): string
    {
        return 'app.performance';
    }

    public static function getTemplate(): string
    {
        return 'performance/collector.html.twig';
    }

    public function isActive(): bool
    {
        return (bool) ($this->data['active'] ?? false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRecord(): ?array
    {
        $record = $this->data['record'] ?? null;

        return \is_array($record) ? $record : null;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
