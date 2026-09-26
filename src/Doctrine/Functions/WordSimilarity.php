<?php

namespace AppBundle\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * pg_trgm's word_similarity(), which scores $needle against the best-matching
 * run of words inside $haystack rather than against the whole of it - unlike
 * Similarity(), its sibling here.
 *
 * That difference is what makes it the right fit for autocomplete: typing one
 * word of a multi-word name still scores well ("legume" against "Fruits &
 * légumes à domicile" scores 0.3, where similarity() gives 0.1).
 *
 * Both arguments are parsed as string primaries, so either may be a column or
 * a parameter - note word_similarity() is not symmetric, and takes the needle
 * first.
 */
class WordSimilarity extends FunctionNode
{
    private $needle;
    private $haystack;

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->needle = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->haystack = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker)
    {
        return sprintf(
            'word_similarity(%s, %s)',
            $sqlWalker->walkStringPrimary($this->needle),
            $sqlWalker->walkStringPrimary($this->haystack)
        );
    }
}
