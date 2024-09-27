<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Delivery;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\RoutingInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CheckDeliveryValidator extends ConstraintValidator
{
    private $storeExtractor;
    private $expressionLanguage;
    private $routing;

    public function __construct(
        TokenStoreExtractor $storeExtractor,
        ExpressionLanguage $expressionLanguage,
        RoutingInterface $routing)
    {
        $this->storeExtractor = $storeExtractor;
        $this->expressionLanguage = $expressionLanguage;
        $this->routing = $routing;
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Delivery) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', Delivery::class));
        }

        if (null === $object->getDistance()) {
            $coords = array_map(fn ($task) => $task->getAddress()->getGeo(), $object->getTasks());
            $distance = $this->routing->getDistance(...$coords);

            $object->setDistance(ceil($distance));
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
                    $this->context->buildViolation($constraint->outOfBoundsMessage)
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
            $this->context->buildViolation($constraint->outOfBoundsMessage)
                ->atPath('items')
                ->addViolation();
        }
    }
}
