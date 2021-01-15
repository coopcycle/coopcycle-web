<?php

namespace AppBundle\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class TsRange extends FunctionNode
{
    private $start = null;
    private $end = null;

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->start = $parser->PathExpression(PathExpression::TYPE_STATE_FIELD);
        $parser->match(Lexer::T_COMMA);
        $this->end = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker)
    {
        return sprintf(
            'tsrange(%s, %s)',
            $sqlWalker->walkPathExpression($this->start),
            $sqlWalker->walkStringPrimary($this->end)
        );
    }
}
