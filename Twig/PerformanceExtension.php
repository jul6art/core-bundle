<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Twig;

use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `core_performance_route_exists('nom')` — la route est-elle déclarée ?
 *
 * Le panneau du profileur propose des liens vers l'écran complet, qui vit dans un AUTRE bundle
 * (`jul6art/admin-bundle`) et dont l'application choisit d'importer les routes — ou non, et
 * généralement en développement seulement. Sans cette question, `path()` lèverait sur toute
 * application qui n'a pas cet écran, et le panneau du profileur casserait la page qu'il profile.
 */
final class PerformanceExtension extends AbstractExtension
{
    /**
     * Le routeur est OPTIONNEL : une application sans composant Routing n'a, par définition,
     * aucune route — et le panneau du profileur ne doit jamais être ce qui casse son conteneur.
     */
    public function __construct(private readonly ?RouterInterface $router = null)
    {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('core_performance_route_exists', $this->routeExists(...)),
        ];
    }

    public function routeExists(string $name): bool
    {
        return null !== $this->router?->getRouteCollection()->get($name);
    }
}
