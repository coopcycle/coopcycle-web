<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\LoopEat\GuestCheckoutAwareAdapter as LoopEatAdapter;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

class LoopeatStockValidator extends ConstraintValidator
{
    public function __construct(private LoopEatClient $loopeatClient)
    {
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof OrderItemInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of "%s"', OrderItemInterface::class));
        }

        $order = $object->getOrder();

        if (!$order->supportsLoopeat()) {
            return;
        }

        if (!$order->isReusablePackagingEnabled()) {
            return;
        }

        $restaurantContainers = $this->loopeatClient->getRestaurantContainers($order);

        $product = $object->getVariant()->getProduct();

        if ($product->isReusablePackagingEnabled()) {

            if (!$product->hasReusablePackagings()) {
                return;
            }

            foreach ($product->getReusablePackagings() as $reusablePackaging) {

                $packagingData = $reusablePackaging->getReusablePackaging()->getData();

                $quantity = ceil($reusablePackaging->getUnits() * $object->getQuantity());
                if ($constraint->useOverridenQuantity &&
                    $object->hasOverridenLoopeatQuantityForPackaging($reusablePackaging->getReusablePackaging())) {
                    $quantity = $object->getOverridenLoopeatQuantityForPackaging($reusablePackaging->getReusablePackaging());
                }

                $missingQuantity = $this->getMissingQuantity($packagingData['id'], $quantity, $restaurantContainers);

                if ($missingQuantity > 0) {
                    $this->context->buildViolation($constraint->message)
                        ->setParameter('%name%', $reusablePackaging->getReusablePackaging()->getName())
                        ->setInvalidValue($missingQuantity)
                        ->setCause($reusablePackaging->getReusablePackaging())
                        ->addViolation();
                }
            }
        }
    }

    private function getMissingQuantity($formatId, $expectedQuantity, $restaurantContainers): int
    {
        $format = current(array_filter($restaurantContainers, function ($format) use ($formatId) {
            return $format['format_id'] === $formatId;
        }));

        $quantityInStock = $format['quantity'];

        $restQuantity = $quantityInStock - $expectedQuantity;

        if ($restQuantity >= 0) {
            return 0;
        }

        return (int) $restQuantity * -1;
    }
}
