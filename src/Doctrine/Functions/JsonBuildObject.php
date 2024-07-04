<?php

namespace AppBundle\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Literal;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class JsonBuildObject extends FunctionNode
{
    private array $values = [];

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->values[] = $parser->ArithmeticPrimary();

        $lexer = $parser->getLexer();

        while ($lexer->lookahead['type'] !== Lexer::T_CLOSE_PARENTHESIS) {
            $parser->match(Lexer::T_COMMA);
            $this->values[] = $parser->ArithmeticPrimary();
        }

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker)
    {
        $parameters = [];
        foreach ($this->values as $node) {
            $parameters[] = $node instanceof Literal ? $sqlWalker->walkLiteral($node) : $sqlWalker->walkPathExpression($node);
        }

        return sprintf(
            'json_build_object(%s)',
            implode(', ', $parameters)
        );
    }
}

