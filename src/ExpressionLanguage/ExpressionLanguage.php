<?php

namespace AppBundle\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as BaseExpressionLanguage;

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
}
