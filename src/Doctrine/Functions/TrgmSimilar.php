<?php

namespace AppBundle\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * pg_trgm's "%" operator: Similarity() expressed as a boolean test, so it can
 * be answered from a GIN gin_trgm_ops index.
 *
 * The same comparison written out - "similarity(a, b) >= 0.3" - is a function
 * call in the filter, which no index can serve, leaving a sequential scan
 * that computes trigram similarity for every row of the table. Only the
 * operator form is indexable, which is the whole reason this exists.
 *
 * The cutoff is not written here: the operator takes it from the
 * pg_trgm.similarity_threshold run-time setting (inclusively - a pair scoring
 * exactly the threshold matches). Callers that depend on a particular cutoff
 * have to set it themselves, on the same connection and inside the same
 * transaction - see OrdersAutocompleteController::customer().
 *
 * DQL has no syntax for an infix operator, so this reads as a function and is
 * used as "TRGM_SIMILAR(x, :q) = TRUE" - the same shape as OVERLAPS() in
 * AppBundle\SearchQuery\Orders. Postgres folds the "= TRUE" away and still
 * uses the index.
 */
class TrgmSimilar extends FunctionNode
{
    private $lhs;
    private $rhs;

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->lhs = $parser->StringPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->rhs = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker)
    {
        return sprintf(
            '(%s %% %s)',
            $sqlWalker->walkStringPrimary($this->lhs),
            $sqlWalker->walkStringPrimary($this->rhs)
        );
    }
}
