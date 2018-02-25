<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class DeliveryValidator extends ConstraintValidator
{
    private $routing;

    public function __construct(RoutingInterface $routing)
    {
        $this->routing = $routing;
    }

    private function validateNotBlank($value, $path)
    {
        $notBlank = new Assert\NotBlank();

        $violations = $this->context->getValidator()->validate($value, $notBlank);
        if (count($violations) > 0) {
            $this->context->buildViolation($notBlank->message)
                ->atPath($path)
                ->addViolation();
        }
    }

    private function validateOrder($object, Constraint $constraint)
    {
        $validator = $this->context->getValidator();
        $now = Carbon::now();

        $order = $object->getOrder();
        $restaurant = $order->getRestaurant();

        $distance = $object->getDistance();
        $duration = $object->getDuration();

        if (null === $distance || null === $duration) {
            $data = $this->routing->getRawResponse(
                $restaurant->getAddress()->getGeo(),
                $object->getDeliveryAddress()->getGeo()
            );
            $distance = $data['routes'][0]['distance'];
            $duration = $data['routes'][0]['duration'];
        }

        $maxDistance = $restaurant->getMaxDistance();

        $violations = $validator->validate($distance, new Assert\LessThan(['value' => $maxDistance]));
        if (count($violations) > 0) {
            $this->context->buildViolation($constraint->addressTooFarMessage)
                ->atPath('deliveryAddress')
                ->addViolation();
        }

        $readyAt = $order->getReadyAt();

        if (null === $readyAt) {
            // Given the time it takes to deliver,
            // calculate when the order should be ready
            $readyAt = clone $object->getDate();
            $readyAt->modify(sprintf('-%d seconds', $duration));
        }

        if ($restaurant->isOpen($readyAt)) {

            $totalDuration = $order->getDuration() + $object->getDuration();
            $timeLeftToPrepare = $readyAt->getTimestamp() - $now->getTimestamp();

            if ($timeLeftToPrepare < $totalDuration) {
                $this->context->buildViolation($constraint->dateTooSoonMessage)
                    ->setParameter('%date%', $object->getDate()->format('Y-m-d H:i:s'))
                    ->atPath('date')
                    ->addViolation();
            }

        } else {
            $this->context->buildViolation($constraint->restaurantClosedMessage)
                ->setParameter('%date%', $readyAt->format('Y-m-d H:i:s'))
                ->atPath('date')
                ->addViolation();
        }
    }

    public function validate($object, Constraint $constraint)
    {
        $validator = $this->context->getValidator();
        $now = Carbon::now();
        $isNew = $object->getId() === null;

        $this->validateNotBlank($object->getDate(), 'date');

        if ($isNew) {
            if ($object->getDate() < $now) {
                $this->context->buildViolation($constraint->dateHasPassedMessage)
                    ->setParameter('%date%', $object->getDate()->format('Y-m-d H:i:s'))
                    ->atPath('date')
                    ->addViolation();
            } else {
                if (null !== $object->getOrder()) {
                    $this->validateOrder($object, $constraint);
                }
            }
        } else {
            if (null !== $object->getOrder()) {
                $this->validateOrder($object, $constraint);
            }
        }
    }
}
