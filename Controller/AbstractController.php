<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Controller;

use Jul6Art\CoreBundle\Service\FlashTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The redirect-and-flash vocabulary a controller of this ecosystem repeats on every write.
 *
 * Each helper flashes a message **already translated in its own domain** — see
 * {@see FlashTranslator} — so a controller never carries a domain name, and never injects the
 * translator just to say "saved".
 *
 * ```php
 * final class UserController extends AbstractController
 * {
 *     public function edit(Request $request, User $user): Response
 *     {
 *         // …
 *         return $this->redirectBackWithSuccess($request, 'user.updated', 'user_index');
 *     }
 * }
 * ```
 *
 * The three redirects worth knowing apart:
 *
 * - {@see redirectBack()} honours the Referer when it is same-origin — for an entity reachable
 *   from several screens, it is the only thing that knows where the user came from.
 * - {@see redirectAfterSave()} reads the `_after_save` field to offer "save and create another".
 * - {@see redirectAfterDelete()} deliberately **ignores** the Referer: after a soft delete the
 *   detail page it points at would 404.
 */
abstract class AbstractController extends SymfonyAbstractController
{
    /**
     * Submit field driving {@see redirectAfterSave()}: `show` (default) or `new`. Render it as a
     * hidden input, or as the value of a second submit button.
     */
    public const string AFTER_SAVE_FIELD = '_after_save';

    /**
     * Translation domain for everything this controller translates — flashes, form errors and
     * {@see trans()} — when the application names its domains **per controller** instead of per
     * key prefix.
     *
     * Returning `null` — the default — leaves the decision to the key prefix, as configured in
     * `core.flash.domain_map`. Override it when the keys carry no domain prefix:
     *
     * ```php
     * final class ProfileController extends AbstractController
     * {
     *     protected function translationDomain(): string   // narrowing the return type is fine
     *     {
     *         return 'profile';   // 'edit.success' → domain 'profile', no prefix needed
     *     }
     * }
     * ```
     *
     * The two conventions coexist: a project mapping prefixes leaves this alone, a project
     * grouping its catalogues by screen overrides it and maps nothing.
     */
    protected function translationDomain(): ?string
    {
        return null;
    }

    /** @param array<string, mixed> $params */
    protected function addSuccessFlash(string $message, array $params = []): void
    {
        $this->addFlash('success', $this->trans($message, $params));
    }

    /** @param array<string, mixed> $params */
    protected function addErrorFlash(string $message, array $params = []): void
    {
        $this->addFlash('error', $this->trans($message, $params));
    }

    /** @param array<string, mixed> $params */
    protected function addWarningFlash(string $message, array $params = []): void
    {
        $this->addFlash('warning', $this->trans($message, $params));
    }

    /**
     * @param array<string, mixed> $params        route parameters
     * @param array<string, mixed> $messageParams placeholders of the flash, e.g. `['%email%' => …]`
     */
    protected function redirectWithSuccess(
        string $route,
        string $message,
        array $params = [],
        array $messageParams = [],
    ): RedirectResponse {
        $this->addSuccessFlash($message, $messageParams);

        return $this->redirectToRoute($route, $params);
    }

    /**
     * @param array<string, mixed> $params        route parameters
     * @param array<string, mixed> $messageParams placeholders of the flash
     */
    protected function redirectWithError(
        string $route,
        string $message,
        array $params = [],
        array $messageParams = [],
    ): RedirectResponse {
        $this->addErrorFlash($message, $messageParams);

        return $this->redirectToRoute($route, $params);
    }

    /**
     * Back to the Referer when it is same-origin, otherwise to the fallback route. A Referer
     * pointing at another host is ignored rather than trusted: following it would be an
     * open-redirect.
     *
     * @param array<string, mixed> $fallbackParams
     */
    protected function redirectBack(Request $request, string $fallbackRoute, array $fallbackParams = []): RedirectResponse
    {
        $referer = $request->headers->get('referer');

        if (null !== $referer && '' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute($fallbackRoute, $fallbackParams);
    }

    /**
     * {@see redirectBack()} with a success flash. Use it for an entity reachable from several
     * screens, so a save keeps the user's context.
     *
     * @param array<string, mixed> $fallbackParams
     * @param array<string, mixed> $messageParams  placeholders of the flash
     */
    protected function redirectBackWithSuccess(
        Request $request,
        string $message,
        string $fallbackRoute,
        array $fallbackParams = [],
        array $messageParams = [],
    ): RedirectResponse {
        $this->addSuccessFlash($message, $messageParams);

        return $this->redirectBack($request, $fallbackRoute, $fallbackParams);
    }

    /**
     * After a successful create, routes on the {@see AFTER_SAVE_FIELD} value: `show` (default)
     * returns to the entity's detail page, `new` lands back on an empty form so the user can
     * keep adding entries without a single click.
     *
     * Asking for `new` when the controller offered no creation route falls back to `show`
     * rather than failing.
     *
     * @param array<string, mixed> $showParams    typically `['id' => …]`
     * @param array<string, mixed> $newParams
     * @param array<string, mixed> $messageParams placeholders of the flash
     */
    protected function redirectAfterSave(
        Request $request,
        string $message,
        string $showRoute,
        array $showParams = [],
        ?string $newRoute = null,
        array $newParams = [],
        array $messageParams = [],
    ): RedirectResponse {
        if ('new' === $request->request->getString(static::AFTER_SAVE_FIELD, 'show') && null !== $newRoute) {
            return $this->redirectWithSuccess($newRoute, $message, $newParams, $messageParams);
        }

        return $this->redirectWithSuccess($showRoute, $message, $showParams, $messageParams);
    }

    /**
     * After a single-row soft delete, always the index. The deleted row's detail page would 404
     * for anyone without the right to see deleted rows, and the user expects the list either
     * way — which is why this does **not** honour the Referer.
     *
     * @param array<string, mixed> $indexParams
     */
    protected function redirectAfterDelete(string $indexRoute, array $indexParams = []): RedirectResponse
    {
        return $this->redirectToRoute($indexRoute, $indexParams);
    }

    /**
     * Attaches a root error to the form, **pre-translated**: `form_errors(form)` looks a key up
     * in the `validators` domain and renders it raw when it is missing, so translating after the
     * fact is too late.
     *
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $params
     */
    protected function addFormError(FormInterface $form, string $key, array $params = []): void
    {
        $form->addError(new FormError($this->trans($key, $params)));
    }

    /**
     * Translates a key in this controller's flash domain — useful beyond flashes: the message of
     * a `createNotFoundException()`, or a string interpolated into another message, belongs to
     * the same catalogue and would otherwise be the last reason to inject the translator.
     *
     * Falls back to the key itself when the bundle's service is unavailable, so a controller
     * built with `new` in a unit test does not blow up.
     *
     * @param array<string, mixed> $params
     */
    protected function trans(string $key, array $params = []): string
    {
        if (!$this->container->has(FlashTranslator::class)) {
            return $key;
        }

        $flashTranslator = $this->container->get(FlashTranslator::class);

        return $flashTranslator instanceof FlashTranslator
            ? $flashTranslator->trans($key, $params, $this->translationDomain())
            : $key;
    }

    /** @return array<string, mixed> */
    public static function getSubscribedServices(): array
    {
        return [...parent::getSubscribedServices(), FlashTranslator::class => FlashTranslator::class];
    }
}
