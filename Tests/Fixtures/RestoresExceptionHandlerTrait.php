<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures;

use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * FrameworkBundle::boot() calls ErrorHandler::register(), which leaves one exception handler on
 * the stack and never removes it. Booting a kernel is this suite's own side effect, so it pops it
 * back off rather than letting PHPUnit report leaked global state.
 *
 * ⚠️ It lives in `Tests/` and not in the exported `Test/` namespace on purpose: the strictness
 * that makes this necessary (`failOnRisky`, `beStrictAboutChangesToGlobalState`) belongs to this
 * bundle's phpunit.xml.dist. None of the consuming projects sets it, and shipping a workaround
 * for a problem they do not have would be answering a question nobody asked.
 */
trait RestoresExceptionHandlerTrait
{
    protected static function restoreSymfonyExceptionHandler(): void
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        if (\is_array($handler) && $handler[0] instanceof ErrorHandler) {
            restore_exception_handler();
        }
    }
}
