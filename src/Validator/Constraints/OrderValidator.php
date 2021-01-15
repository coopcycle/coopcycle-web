<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Address;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\PriceFormatter;
use Carbon\Carbon;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

class OrderValidator extends ConstraintValidator
{
    private $routing;
    private $expressionLanguage;
    private $priceFormatter;

    public function __construct(
        RoutingInterface $routing,
        ExpressionLanguage $expressionLanguage,
        PriceFormatter $priceFormatter)
    {
        $this->routing = $routing;
        $this->expressionLanguage = $expressionLanguage;
        $this->priceFormatter = $priceFormatter;
    }

    private function isAddressValid(Address $address)
    {
        $validator = Validation::createValidator();

        $errors = $validator->validate($address, [
            new Assert\Valid(),
        ]);

        return count($errors) === 0;
    }

    private function validateVendor($object, Constraint $constraint)
    {
        $order = $object;
        $isNew = $order->getId() === null || $order->getState() === OrderInterface::STATE_CART;

        if (!$isNew) {
            return;
        }

        $vendor = $order->getVendor();

        $fulfillmentMethod = $vendor->getFulfillmentMethod($object->getFulfillmentMethod());
        $minimumAmount = $fulfillmentMethod->getMinimumAmount();

        $itemsTotal = $order->getItemsTotal();

        if ($itemsTotal < $minimumAmount) {
            $this->context->buildViolation($constraint->totalIncludingTaxTooLowMessage)
                ->setParameter('%minimum_amount%', $this->priceFormatter->formatWithSymbol($minimumAmount))
                ->atPath('total')
                ->addViolation();

            // Stop here when order is empty
            // We don't want to show an error on shipping address until at least one item is added
            if ($itemsTotal === 0) {
                return;
            }
        }

        $shippingAddress = $order->getShippingAddress();

        if ($order->isTakeaway()) {

            return;
        }

        if (null === $shippingAddress || !$this->isAddressValid($shippingAddress)) {
            $this->context->buildViolation($constraint->addressNotSetMessage)
                ->atPath('shippingAddress')
                ->setCode(Order::ADDRESS_NOT_SET)
                ->addViolation();

            return;
        }

        $distance = $this->routing->getDistance(
            $vendor->getAddress()->getGeo(),
            $shippingAddress->getGeo()
        );

        if (!$vendor->canDeliverAddress($order->getShippingAddress(), $distance, $this->expressionLanguage)) {
            $this->context->buildViolation($constraint->addressTooFarMessage)
                ->atPath('shippingAddress')
                ->setCode(Order::ADDRESS_TOO_FAR)
                ->addViolation();

            return;
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
