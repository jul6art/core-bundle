<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Controller;

use Jul6Art\CoreBundle\Controller\AbstractController;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Exposes the protected helpers so they can be exercised the way a real controller uses them.
 */
final class ConcreteController extends AbstractController
{
    public function success(string $message): void
    {
        $this->addSuccessFlash($message);
    }

    public function error(string $message): void
    {
        $this->addErrorFlash($message);
    }

    public function warning(string $message): void
    {
        $this->addWarningFlash($message);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $messageParameters
     */
    public function toRouteWithSuccess(
        string $route,
        string $message,
        array $parameters = [],
        array $messageParameters = [],
    ): RedirectResponse {
        return $this->redirectWithSuccess($route, $message, $parameters, $messageParameters);
    }

    /** @param array<string, mixed> $parameters */
    public function toRouteWithError(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return $this->redirectWithError($route, $message, $parameters);
    }

    public function back(Request $request, string $fallbackRoute): RedirectResponse
    {
        return $this->redirectBack($request, $fallbackRoute);
    }

    public function backWithSuccess(Request $request, string $message, string $fallbackRoute): RedirectResponse
    {
        return $this->redirectBackWithSuccess($request, $message, $fallbackRoute);
    }

    /**
     * @param array<string, mixed> $showParameters
     * @param array<string, mixed> $newParameters
     */
    public function afterSave(
        Request $request,
        string $message,
        string $showRoute,
        array $showParameters = [],
        ?string $newRoute = null,
        array $newParameters = [],
    ): RedirectResponse {
        return $this->redirectAfterSave($request, $message, $showRoute, $showParameters, $newRoute, $newParameters);
    }

    /** @param array<string, mixed> $parameters */
    public function afterDelete(string $indexRoute, array $parameters = []): RedirectResponse
    {
        return $this->redirectAfterDelete($indexRoute, $parameters);
    }

    /** @param FormInterface<mixed> $form */
    public function formError(FormInterface $form, string $key): void
    {
        $this->addFormError($form, $key);
    }

    /**
     * Lends the wired container to the other fixture, so both conventions are exercised against
     * the same flash translator rather than two subtly different ones.
     */
    public function exposedContainer(): ContainerInterface
    {
        return $this->container;
    }
}
