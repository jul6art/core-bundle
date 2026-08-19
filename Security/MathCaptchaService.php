<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Security;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A home-made arithmetic captcha for public forms — register, password reset — where bots
 * auto-submit payloads just to make the application send mail.
 *
 * It keeps a single integer in the session: the answer to the question currently on screen.
 * {@see generate()} rotates both; {@see validate()} checks the submission and **consumes** the
 * stored answer, so a wrong attempt forces a fresh question and a right one cannot be replayed.
 *
 * Form-only by design. For a JSON client, use reCAPTCHA or hCaptcha instead — a challenge whose
 * answer travels in the same session is worth little to an API consumer.
 */
final class MathCaptchaService
{
    private const string DEFAULT_SESSION_KEY = '_math_captcha_answer';

    /** @var list<string> */
    private readonly array $operations;

    /**
     * @param list<string> $operations subset of `+`, `-`, `*`; anything else is ignored
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        array $operations = ['+'],
        private readonly string $sessionKey = self::DEFAULT_SESSION_KEY,
    ) {
        $allowed = array_values(array_filter($operations, static fn (string $o): bool => \in_array($o, ['+', '-', '*'], true)));

        $this->operations = [] !== $allowed ? $allowed : ['+'];
    }

    /**
     * Draws a new question, stores its answer in the session and returns the text to display.
     */
    public function generate(): string
    {
        $operation = $this->operations[random_int(0, \count($this->operations) - 1)];
        $a = random_int(1, 9);
        $b = random_int(1, 9);

        // A subtraction must never ask for a negative answer: validate() only accepts digits.
        if ('-' === $operation && $b > $a) {
            [$a, $b] = [$b, $a];
        }

        $answer = match ($operation) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
        };

        $this->requestStack->getSession()->set($this->sessionKey, $answer);

        return \sprintf('%d %s %d', $a, $operation, $b);
    }

    /**
     * Checks a submitted answer and consumes the stored one, whatever the outcome.
     */
    public function validate(?string $submitted): bool
    {
        $session = $this->requestStack->getSession();
        $expected = $session->get($this->sessionKey);
        $session->remove($this->sessionKey);

        // The session hands back mixed; anything that is not the integer we stored is treated
        // as no question having been asked.
        if (!\is_int($expected) || null === $submitted) {
            return false;
        }

        $submitted = trim($submitted);

        if ('' === $submitted || !ctype_digit($submitted)) {
            return false;
        }

        return (int) $submitted === $expected;
    }
}
