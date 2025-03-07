<?php

namespace AppBundle\Validator\Constraints;


use AppBundle\Entity\Warehouse;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;


class WarehouseDeleteValidator extends ConstraintValidator
{
    public function __construct(
    ) {}

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Warehouse) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', Warehouse::class));
        }

        if (count($object->getVehicles()) > 0) {
            $this->context
                ->buildViolation(
                    "Vehicles are linked to this warehouse"
                )
                ->atPath('error')
                ->addViolation();
        }
    }
}
