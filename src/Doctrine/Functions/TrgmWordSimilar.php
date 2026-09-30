<?php

namespace AppBundle\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * pg_trgm's "<%" operator - WordSimilarity() as a boolean test, indexable
 * where the function call isn't. See TrgmSimilar for why that matters; this
 * is its word_similarity() counterpart, and like the function it takes the
 * needle first.
 *
 * Its cutoff comes from pg_trgm.word_similarity_threshold, which unlike
 * pg_trgm.similarity_threshold does NOT default to 0.3 - it defaults to 0.6.
 * A caller expecting 0.3 that forgets to set it doesn't get an error, it
 * quietly matches less, so setting it is not optional - see
 * OrdersAutocompleteController::customer().
 */
class TrgmWordSimilar extends FunctionNode
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
            '(%s <%% %s)',
            $sqlWalker->walkStringPrimary($this->needle),
            $sqlWalker->walkStringPrimary($this->haystack)
        );
    }
}
