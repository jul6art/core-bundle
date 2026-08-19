<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Security;

use Jul6Art\CoreBundle\Security\MathCaptchaService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(MathCaptchaService::class)]
final class MathCaptchaServiceTest extends TestCase
{
    private Session $session;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testTheQuestionIsAReadableSum(): void
    {
        self::assertMatchesRegularExpression('#^[1-9] \+ [1-9]$#', $this->service()->generate());
    }

    public function testTheRightAnswerIsAccepted(): void
    {
        $service = $this->service();

        self::assertTrue($service->validate($this->solve($service->generate())));
    }

    public function testAWrongAnswerIsRejected(): void
    {
        $service = $this->service();
        $expected = $this->solve($service->generate());

        self::assertFalse($service->validate((string) ((int) $expected + 1)));
    }

    /**
     * The answer is single-use: a bot that guessed once cannot replay it, and a human who got
     * it wrong is served a fresh question.
     */
    public function testTheAnswerIsConsumedByTheFirstAttempt(): void
    {
        $service = $this->service();
        $answer = $this->solve($service->generate());

        self::assertTrue($service->validate($answer));
        self::assertFalse($service->validate($answer), 'The same answer must not pass twice.');
    }

    public function testAFailedAttemptAlsoConsumesTheAnswer(): void
    {
        $service = $this->service();
        $answer = $this->solve($service->generate());

        self::assertFalse($service->validate('999999'));
        self::assertFalse($service->validate($answer), 'The right answer must not work after a failed attempt.');
    }

    public function testValidatingWithoutAQuestionFails(): void
    {
        self::assertFalse($this->service()->validate('2'));
    }

    /** @return iterable<string, array{string|null}> */
    public static function rejectedInputProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'letters' => ['four'];
        yield 'signed' => ['+4'];
        yield 'decimal' => ['4.0'];
    }

    #[DataProvider('rejectedInputProvider')]
    public function testNonNumericInputIsRejected(?string $input): void
    {
        $service = $this->service();
        $service->generate();

        self::assertFalse($service->validate($input));
    }

    public function testSurroundingWhitespaceIsTolerated(): void
    {
        $service = $this->service();
        $answer = $this->solve($service->generate());

        self::assertTrue($service->validate('  '.$answer.' '));
    }

    // ── opérations configurables ──────────────────────────────────────────

    public function testTheConfiguredOperationsAreTheOnlyOnesUsed(): void
    {
        $service = $this->service(['*']);

        for ($i = 0; $i < 20; ++$i) {
            self::assertMatchesRegularExpression('#^[1-9] \* [1-9]$#', $service->generate());
        }
    }

    /** A subtraction must never ask for a negative answer: the validator only accepts digits. */
    public function testASubtractionNeverYieldsANegativeAnswer(): void
    {
        $service = $this->service(['-']);

        for ($i = 0; $i < 50; ++$i) {
            self::assertGreaterThanOrEqual(0, (int) $this->solve($service->generate()));
        }
    }

    public function testEveryConfiguredOperationSolvesCorrectly(): void
    {
        foreach (['+', '-', '*'] as $operation) {
            $service = $this->service([$operation]);

            self::assertTrue($service->validate($this->solve($service->generate())), 'Operation: '.$operation);
        }
    }

    public function testTheSessionKeyIsConfigurable(): void
    {
        $service = $this->service(sessionKey: '_my_captcha');
        $service->generate();

        self::assertTrue($this->session->has('_my_captcha'));
        self::assertFalse($this->session->has('_math_captcha_answer'));
    }

    /** @param list<string> $operations */
    private function service(array $operations = ['+'], string $sessionKey = '_math_captcha_answer'): MathCaptchaService
    {
        $request = Request::create('/');
        $request->setSession($this->session);

        $stack = new RequestStack([$request]);

        return new MathCaptchaService($stack, $operations, $sessionKey);
    }

    /** Solves the displayed question, the way a human reading the form would. */
    private function solve(string $question): string
    {
        self::assertSame(1, preg_match('#^(\d+) ([+\-*]) (\d+)$#', $question, $m), 'Unexpected question: '.$question);

        [, $a, $operation, $b] = $m;

        return (string) match ($operation) {
            '+' => (int) $a + (int) $b,
            '-' => (int) $a - (int) $b,
            '*' => (int) $a * (int) $b,
        };
    }
}
