<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Security;

use Jul6Art\CoreBundle\Security\CspNonce;
use Jul6Art\CoreBundle\Twig\CspExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(CspNonce::class)]
#[CoversClass(CspExtension::class)]
final class CspNonceTest extends TestCase
{
    /** Every script of a page carries the same nonce: the header names one value. */
    public function testOnePageHasOneNonce(): void
    {
        $nonce = new CspNonce($this->stack(Request::create('/')));

        self::assertSame($nonce->get(), $nonce->get());
        self::assertMatchesRegularExpression('~^[A-Za-z0-9+/]{24}$~', $nonce->get());
    }

    public function testTwoPagesHaveTwoNonces(): void
    {
        self::assertNotSame(
            new CspNonce($this->stack(Request::create('/')))->get(),
            new CspNonce($this->stack(Request::create('/')))->get(),
        );
    }

    /** The listener reads the MAIN request: a fragment's script must land in the page's header. */
    public function testASubRequestDrawsTheNonceOfTheMainRequest(): void
    {
        $main = Request::create('/');
        $stack = $this->stack($main, Request::create('/_fragment'));

        $drawn = new CspNonce($stack)->get();

        self::assertSame($drawn, CspNonce::of($main));
    }

    public function testNothingIsStoredUntilATemplateAsks(): void
    {
        self::assertNull(CspNonce::of(Request::create('/')));
    }

    /** An e-mail rendered by a worker has no request, and no policy to satisfy. */
    public function testWithoutARequestItStillAnswers(): void
    {
        self::assertNotSame('', new CspNonce(new RequestStack())->get());
    }

    public function testTheTwigFunctionWritesTheNonceOfThePage(): void
    {
        $request = Request::create('/');
        $twig = new Environment(new ArrayLoader(['page' => '<script nonce="{{ csp_nonce() }}"></script><script nonce="{{ csp_nonce() }}"></script>']));
        $twig->addExtension(new CspExtension(new CspNonce($this->stack($request))));

        $html = $twig->render('page');
        $nonce = CspNonce::of($request);

        self::assertNotNull($nonce);
        self::assertSame(\sprintf('<script nonce="%1$s"></script><script nonce="%1$s"></script>', $nonce), $html);
    }

    private function stack(Request ...$requests): RequestStack
    {
        $stack = new RequestStack();
        foreach ($requests as $request) {
            $stack->push($request);
        }

        return $stack;
    }
}
