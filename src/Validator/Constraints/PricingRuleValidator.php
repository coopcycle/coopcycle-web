<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\ExpressionLanguage\DeliveryExpressionLanguageVisitor;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use AppBundle\ExpressionLanguage\TaskExpressionLanguageVisitor;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PricingRuleValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly DeliveryExpressionLanguageVisitor $deliveryExpressionLanguageVisitor,
        private readonly TaskExpressionLanguageVisitor $taskExpressionLanguageVisitor,
    )
    {
    }

    /**
     * @param PricingRule $object
     */
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

        $values = [];

        switch ($object->getTarget()) {
            case PricingRule::TARGET_DELIVERY:
                $values = $this->deliveryExpressionLanguageVisitor->toExpressionLanguageValues($delivery);
                break;
            case PricingRule::TARGET_TASK:
                $values = $this->taskExpressionLanguageVisitor->toExpressionLanguageValues($delivery->getPickup());
                break;
            case PricingRule::LEGACY_TARGET_DYNAMIC:
                $values = $this->deliveryExpressionLanguageVisitor->toExpressionLanguageValues($delivery);
                break;
        }

        try {

            $this->expressionLanguage->evaluate($object->getExpression(), $values);

        } catch (SyntaxError $e) {
            $this->context
                ->buildViolation($constraint->expressionSyntaxErrorMessage)
                ->setParameter('%expression%', $object->getExpression() ?? '')
                ->atPath('expression')
                ->addViolation();
        }

        if ($object->isManualSupplement()) {
            // Some manual supplements may use the quantity variable in their price expression
            $values['quantity'] = 1;
        }

        try {

            $this->expressionLanguage->evaluate($object->getPrice(), $values);

        } catch (SyntaxError $e) {

            $this->context
                ->buildViolation($constraint->expressionSyntaxErrorMessage)
                ->setParameter('%expression%', $object->getPrice())
                ->atPath('price')
                ->addViolation();
        }
    }
}
