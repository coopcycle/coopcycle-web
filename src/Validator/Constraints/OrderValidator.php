<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Fulfillment\FulfillmentMethodResolver;
use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\PriceFormatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class OrderValidator extends ConstraintValidator
{

    public function __construct(
        private PriceFormatter $priceFormatter,
        private FulfillmentMethodResolver $fulfillmentMethodResolver,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils,
    )
    {
    }

    private function validateVendor($object, Constraint $constraint)
    {
        $order = $object;
        $isNew = $order->getId() === null || $order->getState() === OrderInterface::STATE_CART;

        if (!$isNew) {
            return;
        }

        $fulfillmentMethod = $this->fulfillmentMethodResolver->resolveForOrder($order);
        $minimumAmount = $fulfillmentMethod->getMinimumAmount();
        $itemsTotal = $order->getItemsTotal();

        if ($itemsTotal < $minimumAmount) {
            $this->context->buildViolation($constraint->totalIncludingTaxTooLowMessage)
                ->setParameter('%minimum_amount%', $this->priceFormatter->formatWithSymbol($minimumAmount))
                ->atPath('total')
                ->addViolation();
        }

        // added to debug the issue with multiple delivery fees: https://github.com/coopcycle/coopcycle-web/issues/3929
        $deliveryAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        if (count($deliveryAdjustments) > 1) {
            $this->context->buildViolation($constraint->unexpectedAdjustmentsCount)
                ->setParameter('%type%', AdjustmentInterface::DELIVERY_ADJUSTMENT)
                ->atPath('adjustments')
                ->addViolation();

            $message = sprintf('Order %s has multiple delivery fees: %d',
                $this->loggingUtils->getOrderId($order),
                count($deliveryAdjustments));

            $this->checkoutLogger->error($message, ['order' => $this->loggingUtils->getOrderId($order)]);
            \Sentry\captureException(new \Exception($message));
        }

        $feeAdjustments = $order->getAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);
        if (count($feeAdjustments) > 1) {
            $this->context->buildViolation($constraint->unexpectedAdjustmentsCount)
                ->setParameter('%type%', AdjustmentInterface::FEE_ADJUSTMENT)
                ->atPath('adjustments')
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
