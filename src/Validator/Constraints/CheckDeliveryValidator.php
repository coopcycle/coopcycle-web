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

        if (null === $store) {
            $store = $object->getStore();
            if (null === $store) {
                $this->context->buildViolation($constraint->noStoreMessage)
                    ->atPath('store')
                    ->addViolation();
            }
        } else {
            // TODO For Woopit the preference is to get the checkExpression from getStore() instead of extractStore()
            if (null !== $object->getStore()) {
                $checkExpression = $object->getStore()->getCheckExpression();

                if (null !== $checkExpression && !$this->expressionLanguage->evaluate($checkExpression, Delivery::toExpressionLanguageValues($object))) {
                    $this->context->buildViolation($constraint->notValidMessage)
                        ->atPath('items')
                        ->addViolation();
                }
            }
        }

        $checkExpression = $store->getCheckExpression();
        if (null === $checkExpression) {
            return;
        }

        if (!$this->expressionLanguage->evaluate($checkExpression, Delivery::toExpressionLanguageValues($object))) {
            $this->context->buildViolation($constraint->notValidMessage)
                ->atPath('items')
                ->addViolation();
        }
    }
}
