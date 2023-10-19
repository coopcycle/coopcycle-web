<?php

namespace AppBundle\Twig;

use AppBundle\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Twig\Extension\RuntimeExtensionInterface;

class ExpressionLanguageRuntime implements RuntimeExtensionInterface
{
    private $expressionLanguage;

    public function __construct(ExpressionLanguage $expressionLanguage)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    public function parseExpression($expression): ParsedExpression
    {
        try {

            return $this->expressionLanguage->parse($expression, [
                'distance',
                'weight',
                'vehicle',
                'pickup',
                'dropoff',
                'packages',
                'order',
                'task',
            ]);

        } catch (SyntaxError $e) {
            return new ParsedExpression('', new Node());
        }
    }
}
