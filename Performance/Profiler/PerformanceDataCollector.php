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

    /**
     * ⚠️ Doit être IDENTIQUE à l'`id` du tag `data_collector` posé par l'extension.
     *
     * Le profileur indexe les collecteurs par ce nom : le tag disant `core.performance` et cette
     * méthode `app.performance`, le panneau ne s'affichait ni dans la barre de debug ni dans le
     * profileur — sans la moindre erreur, puisque le collecteur COLLECTAIT correctement.
     */
    public function getName(): string
    {
        return 'core.performance';
    }

    /**
     * ⚠️ Chemin du bundle, pas du projet. Il valait `performance/collector.html.twig` — le chemin
     * relatif aux templates de l'application d'où ce code vient — et pointait donc vers un
     * gabarit qui n'existe pas ici.
     */
    public static function getTemplate(): string
    {
        return '@Core/performance/collector.html.twig';
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
