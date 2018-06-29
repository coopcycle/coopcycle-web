<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class OrderValidator extends ConstraintValidator
{
    private $routing;

    private $expressionLanguage;

    public function __construct(RoutingInterface $routing, ExpressionLanguage $expressionLanguage)
    {
        $this->routing = $routing;
        $this->expressionLanguage = $expressionLanguage;
    }

    private function validateRestaurant($object, Constraint $constraint)
    {
        $order = $object;
        $restaurant = $order->getRestaurant();

        if (!$restaurant->isOpen($order->getShippedAt())) {
            $this->context->buildViolation($constraint->restaurantClosedMessage)
                ->setParameter('%date%', $order->getShippedAt()->format('Y-m-d H:i:s'))
                ->atPath('shippedAt')
                ->addViolation();

            return;
        }

        if ($order->getShippingAddress()) {
            $data = $this->routing->getRawResponse(
                $restaurant->getAddress()->getGeo(),
                $order->getShippingAddress()->getGeo()
            );

            $distance = $data['routes'][0]['distance'];

            if (!$restaurant->canDeliverAddress($order->getShippingAddress(), $distance, $this->expressionLanguage)) {
                $this->context->buildViolation($constraint->addressTooFarMessage)
                    ->atPath('shippingAddress')
                    ->addViolation();

                return;
            }
        } else {
            $this->context->buildViolation($constraint->addressNotSetMessage)
                ->atPath('shippingAddress')
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
        }
    }

    private function validateStore($object, Constraint $constraint)
    {
        $order = $object;
        $store = $order->getStore();

        if (!$store->isOpen($order->getShippedAt())) {
            $this->context->buildViolation($constraint->restaurantClosedMessage)
                ->setParameter('%date%', $order->getShippedAt()->format('Y-m-d H:i:s'))
                ->atPath('shippedAt')
                ->addViolation();

            return;
        }

        if ($order->getShippingAddress()) {
//            $data = $this->routing->getRawResponse(
//                $store->getAddress()->getGeo(),
//                $order->getShippingAddress()->getGeo()
//            );
//
//            $distance = $data['routes'][0]['distance'];
//
//            if (!$store->canDeliverAddress($order->getShippingAddress(), $distance, $this->expressionLanguage)) {
//                $this->context->buildViolation($constraint->addressTooFarMessage)
//                    ->atPath('shippingAddress')
//                    ->addViolation();
//
//                return;
//            }
        } else {
            $this->context->buildViolation($constraint->addressNotSetMessage)
                ->atPath('shippingAddress')
                ->addViolation();

            return;
        }


//        $minimumAmount = $store->getMinimumCartAmount();
//        $itemsTotal = $order->getItemsTotal();
//
//        if ($itemsTotal < $minimumAmount) {
//            $this->context->buildViolation($constraint->totalIncludingTaxTooLowMessage)
//                ->setParameter('%minimum_amount%', number_format($minimumAmount / 100, 2))
//                ->atPath('total')
//                ->addViolation();
//        }
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof OrderInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', OrderInterface::class));
        }

        $now = Carbon::now();

        $order = $object;
        $isNew = $order->getId() === null;

        if ($isNew && $order->getShippedAt() < $now) {
            $this->context->buildViolation($constraint->dateHasPassedMessage)
                ->setParameter('%date%', $order->getShippedAt()->format('Y-m-d H:i:s'))
                ->atPath('shippedAt')
                ->addViolation();

            return;
        }

        if (null !== $order->getRestaurant()) {
            $this->validateRestaurant($object, $constraint);
        }

        if (null !== $order->getStore()) {
            $this->validateStore($object, $constraint);
        }
    }
}
