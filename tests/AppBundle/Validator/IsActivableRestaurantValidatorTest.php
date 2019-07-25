<?php

namespace AppBundle\Validator;

use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use AppBundle\Service\SettingsManager;
use AppBundle\Entity\Restaurant;
use AppBundle\Validator\Constraints\IsActivableRestaurant;
use AppBundle\Validator\Constraints\IsActivableRestaurantValidator;

class IsActivableRestaurantValidatorTest extends ConstraintValidatorTestCase
{
	private $settingsManager;
	public function setUp() :void
    {
        $this->settingsManager = $this->prophesize(SettingsManager::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new IsActivableRestaurantValidator($this->settingsManager->reveal());
	}

    public function testMissingPhone()
    {
        $restaurant = new Restaurant();
        $restaurant->setName('lorem ipsum');

        $constraint = new IsActivableRestaurant();
        $violations = $this->validator->validate($restaurant, $constraint);

        $this
            ->buildViolation($constraint->telephoneMessage)
            ->atPath('property.path.telephone')
            ->buildNextViolation($constraint->openingHoursMessage)
            ->atPath('property.path.openingHours')
            ->buildNextViolation($constraint->contractMessage)
            ->atPath('property.path.contract')
            ->buildNextViolation($constraint->enabledMessage)
            ->atPath('property.path.enabled')
            ->assertRaised();
    }


    public function testRestaurantInStatePledgeNoErrors()
    {
        $restaurant = new Restaurant();
        $restaurant->setName('lorem ipsum');
        $restaurant->setState('pledge');

        $constraint = new IsActivableRestaurant();
        $violations = $this->validator->validate($restaurant, $constraint);

        $this->assertNoViolation();
    }

}
