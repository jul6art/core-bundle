<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Monolog\Formatter\HtmlFormatter;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bridge\Monolog\Handler\MailerHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks what CoreExtension::prepend() actually produces once MonologBundle has
 * compiled it into real handler services.
 */
#[CoversNothing]
final class MonologPrependTest extends AbstractFunctionalTestCase
{
    /**
     * @param class-string $expectedClass
     */
    #[DataProvider('prodHandlers')]
    public function testTheProdHandlersAreBuilt(string $name, string $expectedClass): void
    {
        $handler = $this->handler($this->boot('prod'), $name);

        self::assertInstanceOf($expectedClass, $handler);
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function prodHandlers(): iterable
    {
        yield 'console' => ['console', ConsoleHandler::class];
        yield 'login' => ['login', StreamHandler::class];
        yield 'main' => ['main', FingersCrossedHandler::class];
        yield 'nested' => ['nested', StreamHandler::class];
        yield 'php' => ['php', StreamHandler::class];
    }

    public function testTheProdHandlersAreNotAddedOutsideProd(): void
    {
        $container = $this->boot('test');

        self::assertFalse($container->has('monolog.handler.login'));
        self::assertFalse($container->has('monolog.handler.nested'));
    }

    public function testNoMailHandlerWithoutEmailDebug(): void
    {
        $container = $this->boot('test');

        self::assertFalse($container->has('monolog.handler.symfony_mailer'));
        self::assertFalse($container->has('core.monolog.html_formatter'));
    }

    public function testTheMailHandlerIsBuiltWhenEmailDebugIsEnabled(): void
    {
        $container = $this->boot('test', $this->emailDebugConfig());

        self::assertInstanceOf(MailerHandler::class, $this->handler($container, 'symfony_mailer'));
        self::assertInstanceOf(DeduplicationHandler::class, $this->handler($container, 'deduplicated'));
    }

    /**
     * MailerHandler only produces an HTML body when its formatter is an
     * HtmlFormatter, and Monolog resolves "formatter" as a service id: passing the
     * bare class name used to break container compilation outright.
     */
    public function testTheMailHandlerFormatterIsAnHtmlFormatter(): void
    {
        $container = $this->boot('test', $this->emailDebugConfig());

        $handler = $this->handler($container, 'symfony_mailer');
        self::assertInstanceOf(FormattableHandlerInterface::class, $handler);

        self::assertInstanceOf(HtmlFormatter::class, $handler->getFormatter());
    }

    public function testEnablingEmailDebugAlsoExposesTheParameters(): void
    {
        $container = $this->boot('test', $this->emailDebugConfig());

        self::assertTrue($container->getParameter('core.email_debug'));
        self::assertSame('from@example.com', $container->getParameter('core.email_debug_from'));
        self::assertSame('to@example.com', $container->getParameter('core.email_debug_to'));
        self::assertSame('Boom', $container->getParameter('core.email_debug_title'));
    }

    public function testAnIncompleteEmailDebugConfigStopsTheBuild(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Parameter "core.email_debug_from"');

        $this->boot('test', ['email_debug' => true, 'email_debug_to' => 'to@example.com']);
    }

    /**
     * @return array<string, mixed>
     */
    private function emailDebugConfig(): array
    {
        return [
            'email_debug' => true,
            'email_debug_from' => 'from@example.com',
            'email_debug_to' => 'to@example.com',
            'email_debug_title' => 'Boom',
        ];
    }

    private function handler(ContainerInterface $container, string $name): HandlerInterface
    {
        $handler = $container->get('monolog.handler.'.$name);

        self::assertInstanceOf(HandlerInterface::class, $handler);

        return $handler;
    }
}
