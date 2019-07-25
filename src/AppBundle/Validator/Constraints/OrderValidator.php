<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Address;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\ShippingDateFilter;
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
    private $shippingDateFilter;

    public function __construct(
        RoutingInterface $routing,
        ExpressionLanguage $expressionLanguage,
        ShippingDateFilter $shippingDateFilter)
    {
        $this->routing = $routing;
        $this->expressionLanguage = $expressionLanguage;
        $this->shippingDateFilter = $shippingDateFilter;
    }

    private function isAddressValid(Address $address)
    {
        $validator = Validation::createValidator();

        $errors = $validator->validate($address, [
            new Assert\Valid(),
        ]);

        return count($errors) === 0;
    }

    private function validateRestaurant($object, Constraint $constraint)
    {
        $order = $object;
        $restaurant = $order->getRestaurant();

        if (null !== $order->getShippedAt() && !$restaurant->isOpen($order->getShippedAt())) {
            $this->context->buildViolation($constraint->restaurantClosedMessage)
                ->setParameter('%date%', $order->getShippedAt()->format('Y-m-d H:i:s'))
                ->atPath('shippedAt')
                ->addViolation();

            return;
        }

        $minimumAmount = $restaurant->getMinimumCartAmount();
        $itemsTotal = $order->getItemsTotal();

        if ($itemsTotal < $minimumAmount) {
            $this->context->buildViolation($constraint->totalIncludingTaxTooLowMessage)
                ->setParameter('%minimum_amount%', number_format($minimumAmount / 100, 2))
                ->atPath('total')
                ->addViolation();

            // Stop here when order is empty
            // We don't want to show an error on shipping address until at least one item is added
            if ($itemsTotal === 0) {
                return;
            }
        }

        $shippingAddress = $order->getShippingAddress();

        if (null === $shippingAddress || !$this->isAddressValid($shippingAddress)) {
            $this->context->buildViolation($constraint->addressNotSetMessage)
                ->atPath('shippingAddress')
                ->setCode(Order::ADDRESS_NOT_SET)
                ->addViolation();

            return;
        }

        $data = $this->routing->getRawResponse(
            $restaurant->getAddress()->getGeo(),
            $shippingAddress->getGeo()
        );

        $distance = $data['routes'][0]['distance'];

        if (!$restaurant->canDeliverAddress($order->getShippingAddress(), $distance, $this->expressionLanguage)) {
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

        // WARNING
        // We use Carbon to be able to mock the date in Behat tests
        $now = Carbon::now();

        $order = $object;
        $isNew = $order->getId() === null || $order->getState() === OrderInterface::STATE_CART;

        if ($isNew) {

            if (null !== $order->getShippedAt()) {
                if ($order->getShippedAt() < $now) {
                    $this->context->buildViolation($constraint->shippedAtExpiredMessage)
                        ->atPath('shippedAt')
                        ->setCode(Order::SHIPPED_AT_EXPIRED)
                        ->addViolation();

                    return;
                }
            }

            if (null !== $order->getShippedAt() && null !== $order->getRestaurant()) {
                if (false === $this->shippingDateFilter->accept($order, $order->getShippedAt(), $now)) {
                    $this->context->buildViolation($constraint->shippedAtNotAvailableMessage)
                        ->atPath('shippedAt')
                        ->setCode(Order::SHIPPED_AT_NOT_AVAILABLE)
                        ->addViolation();

                    return;
                }
            }

            if ($order->containsDisabledProduct()) {
                $this->context->buildViolation($constraint->containsDisabledProductMessage)
                    ->atPath('items')
                    ->setCode(Order::CONTAINS_DISABLED_PRODUCT)
                    ->addViolation();

                return;
            }


        } else {
            if (null === $order->getShippedAt()) {
                $this->context->buildViolation($constraint->shippedAtNotEmptyMessage)
                    ->atPath('shippedAt')
                    ->setCode(Order::SHIPPED_AT_NOT_EMPTY)
                    ->addViolation();

                return;
            }
        }

        if (null !== $order->getRestaurant()) {
            $this->validateRestaurant($object, $constraint);
        }
    }
}
