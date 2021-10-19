<?php

namespace AppBundle\Validator;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Validator\Constraints\Delivery as DeliveryConstraint;
use AppBundle\Validator\Constraints\DeliveryValidator;
use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class DeliveryValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    protected function createValidator()
    {
        return new DeliveryValidator();
    }

    public function testMoreThanTwoTasks()
    {
        $delivery = new Delivery();

        $delivery->addTask(new Task());

        $constraint = new DeliveryConstraint();
        $violations = $this->validator->validate($delivery, $constraint);

        $this->assertNoViolation();
    }

    public function testSecondDropoffBeforePickup()
    {
        $delivery = new Delivery();

        $delivery->addTask(new Task());

        $delivery->getPickup()->setBefore(new \DateTime('+1 hour'));
        $delivery->getDropoff()->setBefore(new \DateTime('+30 minutes'));

        $other = new Task();
        $other->setBefore(new \DateTime('+2 hours'));

        $delivery->addTask($other);

        $constraint = new DeliveryConstraint();
        $violations = $this->validator->validate($delivery, $constraint);

        $this->buildViolation($constraint->pickupAfterDropoffMessage)
            ->atPath('property.path.items')
            ->assertRaised();
    }
}
