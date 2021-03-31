<?php

namespace AppBundle\Twig;

use AppBundle\ExpressionLanguage\ExpressionLanguage;
use Twig\Extension\RuntimeExtensionInterface;

class ExpressionLanguageRuntime implements RuntimeExtensionInterface
{
    private $expressionLanguage;

    public function __construct(ExpressionLanguage $expressionLanguage)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    public function parseExpression($expression)
    {
        return $this->expressionLanguage->parse($expression, [
            'distance',
            'weight',
            'vehicle',
            'pickup',
            'dropoff',
            'packages',
            'order',
        ]);
    }
}
