<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\ShippingDateFilter;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class OrderCustomerValidator extends ConstraintValidator
{
    public function __construct(
        private LoggerInterface $logger)
    {
    }

    public function validate($value, Constraint $constraint)
    {
        $object = $this->context->getObject();

        $this->logger->debug('ORDER CUSTOMER');

        /*
        if (null === $object || !$object instanceof OrderInterface) {
            throw new UnexpectedValueException($object, OrderInterface::class);
        }

        $isNew = $object->getId() === null || $object->getState() === OrderInterface::STATE_CART;

        if (!$isNew) {
            return;
        }

        if (!$object->hasVendor()) {
            return;
        }
        */

        /*
        if ($object->isTakeaway()) {
            return;
        }

        $itemsTotal = $object->getItemsTotal();

        // Stop here when order is empty
        // We don't want to show an error on shipping address until at least one item is added
        if ($itemsTotal === 0) {
            return;
        }

        if (null === $value) {
            $this->context->buildViolation($constraint->addressNotSetMessage)
                ->setCode(ShippingAddress::ADDRESS_NOT_SET)
                ->addViolation();

            return;
        }

        $vendor = $object->getVendor();

        $distance = $this->routing->getDistance(
            $object->getPickupAddress()->getGeo(),
            $value->getGeo()
        );

        if (!$vendor->canDeliverAddress($value, $distance, $this->expressionLanguage)) {
            $this->context->buildViolation($constraint->addressTooFarMessage)
                ->setCode(ShippingAddress::ADDRESS_TOO_FAR)
                ->addViolation();

            return;
        }
        */
    }
}
