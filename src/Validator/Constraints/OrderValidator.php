<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Address;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\PriceFormatter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ValidatorBuilder;

class OrderValidator extends ConstraintValidator
{
    private $priceFormatter;

    public function __construct(PriceFormatter $priceFormatter)
    {
        $this->priceFormatter = $priceFormatter;
    }

    private function validateVendor($object, Constraint $constraint)
    {
        $order = $object;
        $isNew = $order->getId() === null || $order->getState() === OrderInterface::STATE_CART;

        if (!$isNew) {
            return;
        }

        $minimumAmount = $order->getFulfillmentMethodObject()->getMinimumAmount();
        $itemsTotal = $order->getItemsTotal();

        if ($itemsTotal < $minimumAmount) {
            $this->context->buildViolation($constraint->totalIncludingTaxTooLowMessage)
                ->setParameter('%minimum_amount%', $this->priceFormatter->formatWithSymbol($minimumAmount))
                ->atPath('total')
                ->addViolation();
        }
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof OrderInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', OrderInterface::class));
        }

        $order = $object;
        $isNew = $order->getId() === null || $order->getState() === OrderInterface::STATE_CART;

        if ($isNew) {
            if ($order->containsDisabledProduct()) {
                $this->context->buildViolation($constraint->containsDisabledProductMessage)
                    ->atPath('items')
                    ->setCode(Order::CONTAINS_DISABLED_PRODUCT)
                    ->addViolation();

                return;
            }
        } else {
            if (null === $order->getShippingTimeRange()) {
                $this->context->buildViolation($constraint->shippedAtNotEmptyMessage)
                    ->atPath('shippingTimeRange')
                    ->setCode(Order::SHIPPED_AT_NOT_EMPTY)
                    ->addViolation();

                return;
            }
        }

        if ($order->hasVendor()) {
            $this->validateVendor($object, $constraint);
        }
    }
}
