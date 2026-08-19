<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Doctrine\DQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\SqlWalker;
use Jul6Art\CoreBundle\Doctrine\DQL\JsonTextFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The point of the function is portability: PostgreSQL rejects `CAST(json AS CHAR)`
 * and MySQL rejects `json::text`, so the emitted SQL must follow the platform.
 */
#[CoversClass(JsonTextFunction::class)]
final class JsonTextFunctionTest extends TestCase
{
    public function testPostgreSqlUsesTheNativeTextCast(): void
    {
        self::assertSame('u0_.roles::text', $this->sqlFor(new PostgreSQLPlatform()));
    }

    public function testMySqlUsesAnAnsiCast(): void
    {
        self::assertSame('CAST(u0_.roles AS CHAR)', $this->sqlFor(new MySQL80Platform()));
    }

    /** Any other platform falls back to the ANSI form rather than failing. */
    public function testAnyOtherPlatformFallsBackToTheAnsiCast(): void
    {
        self::assertSame('CAST(u0_.roles AS CHAR)', $this->sqlFor(new SQLitePlatform()));
    }

    private function sqlFor(AbstractPlatform $platform): string
    {
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $walker = self::createStub(SqlWalker::class);
        $walker->method('getConnection')->willReturn($connection);

        $field = $this->createMock(PathExpression::class);
        $field->expects(self::atLeastOnce())->method('dispatch')->with($walker)->willReturn('u0_.roles');

        $function = new JsonTextFunction('JSON_TEXT');
        new \ReflectionProperty($function, 'field')->setValue($function, $field);

        return $function->getSql($walker);
    }
}
