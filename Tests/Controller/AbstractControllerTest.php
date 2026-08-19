<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Controller;

use Jul6Art\CoreBundle\Controller\AbstractController;
use Jul6Art\CoreBundle\Service\FlashTranslator;
use Jul6Art\CoreBundle\Tests\Fixtures\Controller\ConcreteController;
use Jul6Art\CoreBundle\Tests\Fixtures\Controller\ScopedController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The helpers a controller of this ecosystem actually calls: a flash pre-translated in the
 * right domain, and a redirect that lands where the user expects.
 */
#[CoversClass(AbstractController::class)]
final class AbstractControllerTest extends TestCase
{
    private Session $session;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new Session(new MockArraySessionStorage());
    }

    // ── flashes ───────────────────────────────────────────────────────────

    public function testASuccessFlashIsTranslatedInItsDomain(): void
    {
        $this->controller()->success('user.created');

        self::assertSame(['user|user.created'], $this->flashes('success'));
    }

    public function testErrorAndWarningUseTheirOwnTypes(): void
    {
        $controller = $this->controller();
        $controller->error('user.failed');
        $controller->warning('user.careful');

        self::assertSame(['user|user.failed'], $this->flashes('error'));
        self::assertSame(['user|user.careful'], $this->flashes('warning'));
    }

    public function testAControllerDomainWinsOverThePrefixMap(): void
    {
        // 'user.' est bien mappé sur 'user', mais ce contrôleur nomme son domaine lui-même :
        // c'est lui qui décide, sinon la carte de préfixes rendrait le crochet inopérant dès
        // qu'une clé ressemble à un préfixe connu.
        $this->scopedController()->success('user.created');

        self::assertSame(['profile|user.created'], $this->flashes('success'));
    }

    public function testAPrefixlessKeyIsTranslatedInTheControllerDomain(): void
    {
        $this->scopedController()->success('edit.success');

        self::assertSame(['profile|edit.success'], $this->flashes('success'));
    }

    public function testARedirectCarriesBothRouteAndMessageParameters(): void
    {
        // Le piège que ce paramètre évite : confondre les deux tableaux. Les paramètres de route
        // fabriquent l'URL, ceux du message remplissent ses substitutions — 104 flashes de superp
        // en ont besoin, et sans cela le raccourci était inutilisable pour eux.
        $response = $this->controller()->toRouteWithSuccess(
            'user_show',
            'user.created',
            ['id' => 7],
            ['%email%' => 'ada@example.com'],
        );

        self::assertSame('/url/user_show?id=7', $response->getTargetUrl());
        self::assertSame(['user|user.created|%email%=ada@example.com'], $this->flashes('success'));
    }

    // ── trans() ───────────────────────────────────────────────────────────

    public function testTransReturnsTheStringInTheControllerDomain(): void
    {
        // Ce que rend possible ce raccourci : le message d'un createNotFoundException() vit dans
        // le même catalogue que les flashes, et c'était la dernière raison d'injecter le
        // traducteur dans un contrôleur.
        self::assertSame('profile|not_found', $this->scopedController()->translate('not_found'));
    }

    public function testTransFallsBackToTheKeyWithoutTheService(): void
    {
        $controller = new ScopedController();
        $controller->setContainer(new Container());

        self::assertSame('not_found', $controller->translate('not_found'));
    }

    public function testAnUnmappedKeyLandsInTheDefaultDomain(): void
    {
        $this->controller()->success('whatever.done');

        self::assertSame(['messages|whatever.done'], $this->flashes('success'));
    }

    // ── redirections simples ──────────────────────────────────────────────

    public function testRedirectWithSuccessFlashesAndRedirects(): void
    {
        $response = $this->controller()->toRouteWithSuccess('user_index', 'user.created');

        self::assertSame('/url/user_index', $response->getTargetUrl());
        self::assertSame(['user|user.created'], $this->flashes('success'));
    }

    public function testRedirectWithErrorFlashesAsAnError(): void
    {
        $this->controller()->toRouteWithError('user_index', 'user.failed');

        self::assertSame(['user|user.failed'], $this->flashes('error'));
    }

    public function testRouteParametersAreForwarded(): void
    {
        $response = $this->controller()->toRouteWithSuccess('user_show', 'user.created', ['id' => 7]);

        self::assertSame('/url/user_show?id=7', $response->getTargetUrl());
    }

    // ── redirectBack ──────────────────────────────────────────────────────

    /**
     * An entity edited from two different screens must return the user where they came from,
     * which the Referer is the only thing that knows.
     */
    public function testItGoesBackToASameOriginReferer(): void
    {
        $request = Request::create('https://app.example.com/user/7/edit');
        $request->headers->set('referer', 'https://app.example.com/dashboard');

        self::assertSame('https://app.example.com/dashboard', $this->controller()->back($request, 'user_index')->getTargetUrl());
    }

    /**
     * A Referer from another host is an open-redirect vector, so it is ignored rather than
     * trusted.
     */
    public function testAForeignRefererIsIgnored(): void
    {
        $request = Request::create('https://app.example.com/user/7/edit');
        $request->headers->set('referer', 'https://evil.example.net/steal');

        self::assertSame('/url/user_index', $this->controller()->back($request, 'user_index')->getTargetUrl());
    }

    public function testWithoutARefererItFallsBackToTheRoute(): void
    {
        self::assertSame('/url/user_index', $this->controller()->back(Request::create('/user/7/edit'), 'user_index')->getTargetUrl());
    }

    public function testGoingBackCanCarryASuccessFlash(): void
    {
        $request = Request::create('https://app.example.com/user/7/edit');
        $request->headers->set('referer', 'https://app.example.com/dashboard');

        $response = $this->controller()->backWithSuccess($request, 'user.created', 'user_index');

        self::assertSame('https://app.example.com/dashboard', $response->getTargetUrl());
        self::assertSame(['user|user.created'], $this->flashes('success'));
    }

    // ── redirectAfterSave ─────────────────────────────────────────────────

    public function testAfterSaveGoesToTheDetailPageByDefault(): void
    {
        $response = $this->controller()->afterSave(Request::create('/user/new', Request::METHOD_POST), 'user.created', 'user_show', ['id' => 7]);

        self::assertSame('/url/user_show?id=7', $response->getTargetUrl());
    }

    /** "Save and create another": the field is what drives the keyboard-friendly workflow. */
    public function testAfterSaveHonoursTheRequestToCreateAnother(): void
    {
        $request = Request::create('/user/new', Request::METHOD_POST, [AbstractController::AFTER_SAVE_FIELD => 'new']);

        $response = $this->controller()->afterSave($request, 'user.created', 'user_show', ['id' => 7], 'user_new');

        self::assertSame('/url/user_new', $response->getTargetUrl());
    }

    /** Asking for a form the controller did not offer must not 500; it falls back. */
    public function testAfterSaveFallsBackWhenNoCreationRouteWasGiven(): void
    {
        $request = Request::create('/user/new', Request::METHOD_POST, [AbstractController::AFTER_SAVE_FIELD => 'new']);

        $response = $this->controller()->afterSave($request, 'user.created', 'user_show', ['id' => 7]);

        self::assertSame('/url/user_show?id=7', $response->getTargetUrl());
    }

    public function testAfterSaveAlwaysFlashesTheSuccess(): void
    {
        $this->controller()->afterSave(Request::create('/user/new', Request::METHOD_POST), 'user.created', 'user_show');

        self::assertSame(['user|user.created'], $this->flashes('success'));
    }

    // ── redirectAfterDelete ───────────────────────────────────────────────

    /**
     * After a soft delete the detail page would 404 for anyone but a super-admin, so this one
     * deliberately ignores the Referer and lands on the index.
     */
    public function testAfterDeleteAlwaysLandsOnTheIndex(): void
    {
        self::assertSame('/url/user_index', $this->controller()->afterDelete('user_index')->getTargetUrl());
    }

    // ── addFormError ──────────────────────────────────────────────────────

    /**
     * `form_errors(form)` looks the key up in the `validators` domain and renders it raw when it
     * is missing, so the message has to be translated *before* it reaches the form.
     */
    public function testAFormErrorIsPreTranslated(): void
    {
        $form = $this->form();

        $this->controller()->formError($form, 'user.email_taken');

        self::assertCount(1, $form->getErrors());
        self::assertSame('user|user.email_taken', $form->getErrors()[0]->getMessage());
    }

    private function controller(): ConcreteController
    {
        $request = Request::create('/');
        $request->setSession($this->session);

        $stack = new RequestStack([$request]);

        $router = self::createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/url/'.$route.([] === $parameters ? '' : '?'.http_build_query($parameters))
        );

        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $parameters, ?string $domain): string {
                $rendered = ($domain ?? 'null').'|'.$id;

                foreach ($parameters as $key => $value) {
                    $rendered .= '|'.$key.'='.(\is_scalar($value) ? (string) $value : '?');
                }

                return $rendered;
            }
        );

        $container = new Container();
        $container->set('request_stack', $stack);
        $container->set('router', $router);
        $container->set(FlashTranslator::class, new FlashTranslator($translator, ['user.' => 'user'], 'messages'));

        $controller = new ConcreteController();
        $controller->setContainer($container);

        return $controller;
    }

    private function scopedController(): ScopedController
    {
        $controller = new ScopedController();
        $controller->setContainer($this->controller()->exposedContainer());

        return $controller;
    }

    /** @return list<string> */
    private function flashes(string $type): array
    {
        $bag = $this->session->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $bag);

        /** @var list<string> $messages */
        $messages = $bag->peek($type);

        return $messages;
    }

    /** @return FormInterface<mixed> */
    private function form(): FormInterface
    {
        return Forms::createFormFactory()->create();
    }
}
