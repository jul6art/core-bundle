<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Doctrine\DQL;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL function `JSON_TEXT(field)`: casts a JSON column to text so a portable `LIKE`
 * search can run over the serialized payload — useful for filtering a JSON-array column
 * such as `User.roles` by membership.
 *
 * Platform-aware, because the two forms are mutually exclusive: PostgreSQL rejects
 * `CAST(json AS CHAR)` and MySQL rejects `json::text`.
 *
 *   - PostgreSQL → `field::text`
 *   - anything else (MySQL, MariaDB, SQLite, …) → `CAST(field AS CHAR)`, the ANSI form
 *
 * Register it from the application:
 *
 * ```yaml
 * doctrine:
 *     orm:
 *         dql:
 *             string_functions:
 *                 JSON_TEXT: Jul6Art\CoreBundle\Doctrine\DQL\JsonTextFunction
 * ```
 *
 * Usage in DQL: `JSON_TEXT(u.roles) LIKE :param`.
 */
final class JsonTextFunction extends FunctionNode
{
    /** Set by {@see self::parse()}; reading it before that is a programming error. */
    private PathExpression $field;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->field = $parser->StateFieldPathExpression();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $fieldSql = $this->field->dispatch($sqlWalker);
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            return $fieldSql.'::text';
        }

        // MySQL, MariaDB, SQLite and others: the ANSI CAST. Each platform converts
        // CHAR to its own string type, and the downstream LIKE then matches the
        // serialized JSON.
        return \sprintf('CAST(%s AS CHAR)', $fieldSql);
    }
}
