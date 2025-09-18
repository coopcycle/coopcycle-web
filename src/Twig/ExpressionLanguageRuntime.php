<?php

namespace AppBundle\Twig;

use AppBundle\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Twig\Extension\RuntimeExtensionInterface;

class ExpressionLanguageRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
    ) {
    }

    public function parseRuleExpression($expression): ParsedExpression
    {
        $expressionAst = $this->expressionLanguage->parseRuleExpression($expression);

        if (null === $expressionAst) {
            return new ParsedExpression('', new Node());
        }

        return $expressionAst;
    }

    public function parsePrice($expression): ParsedExpression
    {
        $expressionAst = $this->expressionLanguage->parsePrice($expression);

        if (null === $expressionAst) {
            return new ParsedExpression('', new Node());
        }

        return $expressionAst;
    }
}
