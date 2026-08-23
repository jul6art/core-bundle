<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Aucune route : le kernel de test n'a besoin du ROUTEUR, pas de routes.
 *
 * Le panneau du profileur utilise `path()`, donc le composant Routing doit être là pour que le
 * gabarit compile — c'est ce que ce fichier permet de vérifier. Et l'absence de route est
 * exactement le cas que `core_performance_route_exists()` doit savoir gérer : l'écran complet
 * vit dans un autre bundle, que l'application est libre de ne pas importer.
 */
return static function (RoutingConfigurator $routes): void {
};
