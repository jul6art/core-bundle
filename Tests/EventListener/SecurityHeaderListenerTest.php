<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\EventListener;

use Jul6Art\CoreBundle\EventListener\SecurityHeaderListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(SecurityHeaderListener::class)]
final class SecurityHeaderListenerTest extends TestCase
{
    public function testTheHardHeadersAreSetOnEveryResponse(): void
    {
        $response = $this->respond();

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertStringContainsString('geolocation=()', (string) $response->headers->get('Permissions-Policy'));
        self::assertStringContainsString('max-age=31536000', (string) $response->headers->get('Strict-Transport-Security'));
    }

    /**
     * Installing a utility bundle must not change the HTTP responses of an application that
     * did not ask: `X-Frame-Options: DENY` alone would break any legitimate embedding.
     */
    public function testItDoesNothingUntilItIsEnabled(): void
    {
        $response = $this->respond(enabled: false);

        self::assertFalse($response->headers->has('X-Content-Type-Options'));
        self::assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    // ── CSP ───────────────────────────────────────────────────────────────

    public function testTheCspStartsInReportOnlyMode(): void
    {
        $response = $this->respond();

        self::assertTrue($response->headers->has('Content-Security-Policy-Report-Only'));
        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testTheCspIsEnforcedWhenAsked(): void
    {
        $response = $this->respond(cspEnforce: true);

        self::assertTrue($response->headers->has('Content-Security-Policy'));
        self::assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function testAConfiguredPolicyReplacesTheDefaultOne(): void
    {
        $response = $this->respond(cspEnforce: true, cspPolicy: "default-src 'none'");

        self::assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    /**
     * A library cannot know which third-party hosts an application talks to, so the default
     * policy stays closed and each project widens it.
     */
    public function testTheDefaultPolicyKeepsConnectSrcClosed(): void
    {
        $policy = (string) $this->respond()->headers->get('Content-Security-Policy-Report-Only');

        self::assertStringContainsString("default-src 'self'", $policy);
        self::assertStringContainsString("connect-src 'self'", $policy);
        self::assertStringNotContainsString('http:', $policy);
        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    // ── surcharges ────────────────────────────────────────────────────────

    public function testAConfiguredHeaderOverridesTheDefault(): void
    {
        $response = $this->respond(headers: ['X-Frame-Options' => 'SAMEORIGIN']);

        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    /** A null value is how a project drops a header it does not want at all. */
    public function testANullHeaderIsNotSent(): void
    {
        $response = $this->respond(headers: ['Strict-Transport-Security' => null]);

        self::assertFalse($response->headers->has('Strict-Transport-Security'));
        self::assertTrue($response->headers->has('X-Content-Type-Options'), 'The others must still be set.');
    }

    public function testAnExtraHeaderCanBeAdded(): void
    {
        $response = $this->respond(headers: ['X-Robots-Tag' => 'noindex']);

        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    /**
     * Only missing headers are filled: a controller that set its own — a CMS preview needing
     * SAMEORIGIN to survive its own iframe — keeps it.
     */
    public function testAHeaderAlreadySetByTheApplicationIsLeftAlone(): void
    {
        $response = new Response();
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        $this->listener()->onKernelResponse($this->event($response));

        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function testSubRequestsAreIgnored(): void
    {
        $response = new Response();

        $this->listener()->onKernelResponse($this->event($response, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($response->headers->has('X-Content-Type-Options'));
    }

    /** @param array<string, string|null> $headers */
    private function respond(
        bool $enabled = true,
        bool $cspEnforce = false,
        ?string $cspPolicy = null,
        array $headers = [],
    ): Response {
        $response = new Response();

        $this->listener($enabled, $cspEnforce, $cspPolicy, $headers)->onKernelResponse($this->event($response));

        return $response;
    }

    /** @param array<string, string|null> $headers */
    private function listener(
        bool $enabled = true,
        bool $cspEnforce = false,
        ?string $cspPolicy = null,
        array $headers = [],
    ): SecurityHeaderListener {
        return new SecurityHeaderListener($enabled, $cspEnforce, $cspPolicy, $headers);
    }

    private function event(Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent(self::createStub(HttpKernelInterface::class), Request::create('/'), $type, $response);
    }
}
