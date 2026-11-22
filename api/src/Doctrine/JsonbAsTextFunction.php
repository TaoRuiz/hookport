<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL "JSONB_AS_TEXT(field)", rendered as PostgreSQL "field::text" so a jsonb column can be searched with LIKE.
 */
final class JsonbAsTextFunction extends FunctionNode
{
    private Node $jsonbField;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return '('.$sqlWalker->walkStringPrimary($this->jsonbField).')::text';
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonbField = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
