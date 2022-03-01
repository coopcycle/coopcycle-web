<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PricingRuleValidator extends ConstraintValidator
{
    private $expressionLanguage;

    public function __construct(ExpressionLanguage $expressionLanguage)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof PricingRule) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', PricingRule::class));
        }

        $delivery = new Delivery();

        $after = new \DateTime('+3 hours');
        $before = new \DateTime('+4 hours');

        $delivery->getPickup()->setAfter($after);
        $delivery->getPickup()->setBefore($before);

        $delivery->getDropoff()->setAfter($after);
        $delivery->getDropoff()->setBefore($before);

        try {

            $this->expressionLanguage->evaluate($object->getExpression(), Delivery::toExpressionLanguageValues($delivery));

        } catch (SyntaxError $e) {
            $this->context
                ->buildViolation($constraint->expressionSyntaxErrorMessage)
                ->setParameter('%expression%', $object->getExpression() ?? '')
                ->atPath('expression')
                ->addViolation();
        }

        try {

            $this->expressionLanguage->evaluate($object->getPrice(), Delivery::toExpressionLanguageValues($delivery));

        } catch (SyntaxError $e) {

            $this->context
                ->buildViolation($constraint->expressionSyntaxErrorMessage)
                ->setParameter('%expression%', $object->getPrice())
                ->atPath('price')
                ->addViolation();
        }
    }
}
