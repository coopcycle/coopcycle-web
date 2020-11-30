<?php

namespace AppBundle\Validator;

use AppBundle\DataType\TsRange;
use AppBundle\Validator\Constraints\ShippingTimeRangeJump as ShippingTimeRangeJumpConstraint;
use AppBundle\Validator\Constraints\ShippingTimeRangeJumpValidator;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\ValidatorBuilder;

class ShippingTimeRangeJumpValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    public function setUp() :void
    {
        parent::setUp();
    }

    protected function createValidator()
    {
        return new ShippingTimeRangeJumpValidator();
    }

    public function testNextDay()
    {
        $displayed  = TsRange::parse('2020-11-29T22:25:00+01:00 - 2020-11-29T22:35:00+01:00');
        $calculated = TsRange::parse('2020-11-30T10:55:00+01:00 - 2020-11-30T11:05:00+01:00');

        $constraint = new ShippingTimeRangeJumpConstraint();
        $violations = $this->validator->validate([$displayed, $calculated], $constraint);

        $this->buildViolation($constraint->nextDayMessage)
            ->assertRaised();
    }

    public function testIsValid()
    {
        $displayed  = TsRange::parse('2020-11-30T10:55:00+01:00 - 2020-11-30T11:05:00+01:00');
        $calculated = TsRange::parse('2020-11-30T10:55:00+01:00 - 2020-11-30T11:05:00+01:00');

        $constraint = new ShippingTimeRangeJumpConstraint();
        $violations = $this->validator->validate([$displayed, $calculated], $constraint);

        $this->assertNoViolation();
    }
}
