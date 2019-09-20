<?php

namespace AppBundle\Validator;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\SettingsManager;
use AppBundle\Validator\Constraints\IsActivableRestaurant;
use AppBundle\Validator\Constraints\IsActivableRestaurantValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

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

    public function testCatererNoErrors()
    {
        $contract = new Contract();
        $contract->setFeeRate(20.00);
        $contract->setCustomerAmount(350);
        $contract->setMinimumCartAmount(1500);
        $contract->setFlatDeliveryPrice(350);

        $restaurant = new Restaurant();
        $restaurant->setName('lorem ipsum');
        $restaurant->setTelephone('+33612345678');
        $restaurant->setOpeningHours(['Mo-Fr 10:45-13:30']);
        $restaurant->setContract($contract);

        $constraint = new IsActivableRestaurant();
        $violations = $this->validator->validate($restaurant, $constraint);

        $this->assertNoViolation();
    }
}
