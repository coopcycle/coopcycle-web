<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Delivery;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use AppBundle\Security\TokenStoreExtractor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CheckDeliveryValidator extends ConstraintValidator
{
    private $storeExtractor;
    private $expressionLanguage;

    public function __construct(
        TokenStoreExtractor $storeExtractor,
        ExpressionLanguage $expressionLanguage)
    {
        $this->storeExtractor = $storeExtractor;
        $this->expressionLanguage = $expressionLanguage;
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Delivery) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', Delivery::class));
        }

        // TODO Also resolve store from getStore() method
        $store = $this->storeExtractor->extractStore();

        $checkExpression = $store->getCheckExpression();
        if (null === $checkExpression) {
            return;
        }

        if (!$this->expressionLanguage->evaluate($checkExpression, Delivery::toExpressionLanguageValues($object))) {
            $this->context->buildViolation($constraint->message)
                 ->atPath('items')
                 ->addViolation();
        }
    }
}
