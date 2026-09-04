<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Translation;

use Jul6Art\CoreBundle\Test\AbstractJsTranslationTestCase;
use Jul6Art\CoreBundle\Tests\Fixtures\JsGuardKernel;
use Jul6Art\CoreBundle\Tests\Fixtures\RestoresExceptionHandlerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Runs the guard the four projects will extend, against a fixture application.
 *
 * The audit's own rules are proved in {@see JsTranslationAuditTest}; what this proves is the
 * wiring — that the base class finds the locales, the domain and the translator on its own, so a
 * project's subclass is the three lines its docblock promises.
 */
#[CoversClass(AbstractJsTranslationTestCase::class)]
final class JsTranslationGuardTest extends AbstractJsTranslationTestCase
{
    use RestoresExceptionHandlerTrait;

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        self::restoreSymfonyExceptionHandler();
    }

    #[\Override]
    protected static function javaScriptDirectories(): array
    {
        return [self::projectDir().'/Tests/Fixtures/assets'];
    }

    #[\Override]
    protected static function templateDirectories(): array
    {
        return [self::projectDir().'/Tests/Fixtures/templates'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new JsGuardKernel();
    }
}
