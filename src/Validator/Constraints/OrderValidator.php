<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Fulfillment\FulfillmentMethodResolver;
use AppBundle\Service\LoggingUtils;
use AppBundle\Service\NullLoggingUtils;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\PriceFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class OrderValidator extends ConstraintValidator
{
    private LoggerInterface $checkoutLogger;
    private LoggingUtils $loggingUtils;

    public function __construct(
        private PriceFormatter $priceFormatter,
        private FulfillmentMethodResolver $fulfillmentMethodResolver,
        LoggerInterface $checkoutLogger = null,
        LoggingUtils $loggingUtils = null,
    )
    {
        $this->checkoutLogger = $checkoutLogger ?? new NullLogger();
        $this->loggingUtils = $loggingUtils ?? new NullLoggingUtils();
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

                return;
            }

            if ($order->hasVendor()) {
                $fulfillmentMethod = $this->fulfillmentMethodResolver->resolveForOrder($order);

                $minimumAmount = $fulfillmentMethod->getMinimumAmount();
                $itemsTotal = $order->getItemsTotal();

                if (!$fulfillmentMethod->isEnabled()) {
                    $this->context->buildViolation($constraint->fulfillmentMethodDisabledMessage)
                        ->atPath('fulfillmentMethod')
                        ->setCode(Order::FULFILMENT_METHOD_DISABLED)
                        ->addViolation();
                    return;
                }

                if ($itemsTotal < $minimumAmount) {
                    $this->context->buildViolation($constraint->totalIncludingTaxTooLowMessage)
                        ->setParameter('%minimum_amount%', $this->priceFormatter->formatWithSymbol($minimumAmount))
                        ->atPath('total')
                        ->addViolation();
                }
            }

        } else {
            if (null === $order->getShippingTimeRange()) {
                $this->context->buildViolation($constraint->shippedAtNotEmptyMessage)
                    ->atPath('shippingTimeRange')
                    ->setCode(Order::SHIPPED_AT_NOT_EMPTY)
                    ->addViolation();
            }
        }
    }
}
