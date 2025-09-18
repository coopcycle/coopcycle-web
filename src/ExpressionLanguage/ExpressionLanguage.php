<?php

namespace AppBundle\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as BaseExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Adds some function to the default Symfony ExpressionLanguage.
 */
class ExpressionLanguage extends BaseExpressionLanguage
{
    protected function registerFunctions()
    {
        parent::registerFunctions();

        $this->addFunction(ExpressionFunction::fromPhp('ceil'));
        $this->addFunction(ExpressionFunction::fromPhp('floor'));
    }

    public function parseRuleExpression($expression): ?ParsedExpression
    {
        try {

            return $this->parse($expression, [
                'distance',
                'weight',
                'vehicle',
                'pickup',
                'dropoff',
                'packages',
                'order',
                'task',
                'time_slot',
            ]);

        } catch (SyntaxError $e) {
            return null;
        }
    }

    public function parsePrice($expression): ?ParsedExpression
    {
        try {

            return $this->parse($expression, [
                'distance',
                'weight',
                'packages',
                'quantity' // manual supplement
            ]);

        } catch (SyntaxError $e) {
            return null;
        }
    }
}
