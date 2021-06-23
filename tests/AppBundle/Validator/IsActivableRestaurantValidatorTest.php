<?php

namespace AppBundle\Validator;

use AppBundle\Entity\Contract;
use AppBundle\Entity\Restaurant;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\SettingsManager;
use AppBundle\Validator\Constraints\IsActivableRestaurant;
use AppBundle\Validator\Constraints\IsActivableRestaurantValidator;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class IsActivableRestaurantValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

	private $settingsManager;
    private $gatewayResolver;

	public function setUp() :void
    {
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new IsActivableRestaurantValidator(
            $this->settingsManager->reveal(),
            $this->gatewayResolver->reveal(),
            $cashEnabled = false
        );
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
            ->atPath('property.path.fulfillmentMethods[0].openingHours')
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

    public function testNoErrors()
    {
        $contract = new Contract();
        $contract->setFeeRate(20.00);
        $contract->setCustomerAmount(350);
        $contract->setFlatDeliveryPrice(350);

        $restaurant = new Restaurant();
        $restaurant->setName('lorem ipsum');
        $restaurant->setTelephone('+33612345678');
        $restaurant->setContract($contract);

        foreach ($restaurant->getFulfillmentMethods() as $fulfillmentMethod) {
            if ($fulfillmentMethod->isEnabled()) {
                $fulfillmentMethod->addOpeningHour('Mo-Su 12:00-14:00');
            }
        }

        $constraint = new IsActivableRestaurant();
        $violations = $this->validator->validate($restaurant, $constraint);

        $this->assertNoViolation();
    }
}
