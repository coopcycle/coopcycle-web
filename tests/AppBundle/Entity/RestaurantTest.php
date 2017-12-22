<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\ClosingRule;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\ValidationUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class RestaurantTest extends TestCase
{
    private $validator;

    public function setUp()
    {
        $this->validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
    }

    public function testGetAvailabilities() {
        $restaurant = new Restaurant();
        $restaurant->setOpeningHours(["Mo-Sa 10:00-19:00"]);

        $date = new \DateTime('2017-10-04T17:30:00+02:00');

        $availabilities = $restaurant->getAvailabilities($date);

        $this->assertEquals([
            '2017-10-04T18:30:00+02:00',
            '2017-10-04T18:45:00+02:00',
            '2017-10-04T19:00:00+02:00',
            '2017-10-05T10:00:00+02:00',
            '2017-10-05T10:15:00+02:00',
            '2017-10-05T10:30:00+02:00',
            '2017-10-05T10:45:00+02:00',
            '2017-10-05T11:00:00+02:00',
            '2017-10-05T11:15:00+02:00',
            '2017-10-05T11:30:00+02:00',
            '2017-10-05T11:45:00+02:00',
            '2017-10-05T12:00:00+02:00',
            '2017-10-05T12:15:00+02:00',
            '2017-10-05T12:30:00+02:00',
            '2017-10-05T12:45:00+02:00',
            '2017-10-05T13:00:00+02:00',
            '2017-10-05T13:15:00+02:00',
            '2017-10-05T13:30:00+02:00',
            '2017-10-05T13:45:00+02:00',
            '2017-10-05T14:00:00+02:00',
            '2017-10-05T14:15:00+02:00',
            '2017-10-05T14:30:00+02:00',
            '2017-10-05T14:45:00+02:00',
            '2017-10-05T15:00:00+02:00',
            '2017-10-05T15:15:00+02:00',
            '2017-10-05T15:30:00+02:00',
            '2017-10-05T15:45:00+02:00',
            '2017-10-05T16:00:00+02:00',
            '2017-10-05T16:15:00+02:00',
            '2017-10-05T16:30:00+02:00',
            '2017-10-05T16:45:00+02:00',
            '2017-10-05T17:00:00+02:00',
            '2017-10-05T17:15:00+02:00',
            '2017-10-05T17:30:00+02:00',
            '2017-10-05T17:45:00+02:00',
            '2017-10-05T18:00:00+02:00',
            '2017-10-05T18:15:00+02:00',
            '2017-10-05T18:30:00+02:00',
            '2017-10-05T18:45:00+02:00',
            '2017-10-05T19:00:00+02:00'
        ], $availabilities);

    }

    /**
     * Testcase : when the number of seconds is not equal to 0, round properly to next minute
     */
    public function testGetAvailabilitiesWithSecondRoundings() {
        $restaurant = new Restaurant();
        $restaurant->setOpeningHours(["Mo-Sa 10:00-19:00"]);


        $date = new \DateTime('2017-10-04T17:30:26+02:00');

        $availabilities = $restaurant->getAvailabilities($date);

        $this->assertEquals([
            '2017-10-04T18:30:00+02:00',
            '2017-10-04T18:45:00+02:00',
            '2017-10-04T19:00:00+02:00',
            '2017-10-05T10:00:00+02:00',
            '2017-10-05T10:15:00+02:00',
            '2017-10-05T10:30:00+02:00',
            '2017-10-05T10:45:00+02:00',
            '2017-10-05T11:00:00+02:00',
            '2017-10-05T11:15:00+02:00',
            '2017-10-05T11:30:00+02:00',
            '2017-10-05T11:45:00+02:00',
            '2017-10-05T12:00:00+02:00',
            '2017-10-05T12:15:00+02:00',
            '2017-10-05T12:30:00+02:00',
            '2017-10-05T12:45:00+02:00',
            '2017-10-05T13:00:00+02:00',
            '2017-10-05T13:15:00+02:00',
            '2017-10-05T13:30:00+02:00',
            '2017-10-05T13:45:00+02:00',
            '2017-10-05T14:00:00+02:00',
            '2017-10-05T14:15:00+02:00',
            '2017-10-05T14:30:00+02:00',
            '2017-10-05T14:45:00+02:00',
            '2017-10-05T15:00:00+02:00',
            '2017-10-05T15:15:00+02:00',
            '2017-10-05T15:30:00+02:00',
            '2017-10-05T15:45:00+02:00',
            '2017-10-05T16:00:00+02:00',
            '2017-10-05T16:15:00+02:00',
            '2017-10-05T16:30:00+02:00',
            '2017-10-05T16:45:00+02:00',
            '2017-10-05T17:00:00+02:00',
            '2017-10-05T17:15:00+02:00',
            '2017-10-05T17:30:00+02:00',
            '2017-10-05T17:45:00+02:00',
            '2017-10-05T18:00:00+02:00',
            '2017-10-05T18:15:00+02:00',
            '2017-10-05T18:30:00+02:00',
            '2017-10-05T18:45:00+02:00',
            '2017-10-05T19:00:00+02:00'
        ], $availabilities);

    }

    public function testGetAvailabilitiesWithNoOpenings() {
        $restaurant = new Restaurant();
        $restaurant->setOpeningHours([]);


        $date = new \DateTime('2017-10-04T17:30:26+02:00');

        $availabilities = $restaurant->getAvailabilities($date);

        $this->assertEquals([], $availabilities);

    }

    public function testValidationErrors() {
        $restaurant = new Restaurant();

        $violations = $this->validator->validate($restaurant, null, ['activable']);
        $errors = ValidationUtils::serializeValidationErrors($violations);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('telephone', $errors);
        $this->assertArrayHasKey('openingHours', $errors);
        $this->assertArrayHasKey('contract', $errors);
    }

    public function testCannotEnableRestaurant() {
        $restaurant = new Restaurant();
        $restaurant->setEnabled(true);

        $violations = $this->validator->validate($restaurant, null, ['activable']);
        $errors = ValidationUtils::serializeValidationErrors($violations);

        $this->assertArrayHasKey('enabled', $errors);
    }

    public function testClosingRuleFilterAvailabilities() {
        $restaurant = new Restaurant();
        $restaurant->setOpeningHours(["Mo-Sa 10:00-19:00"]);

        $closingRule = new ClosingRule();
        $closingRule->setStartDate(new \DateTime('2017-10-04T18:29:26+02:00'));
        $closingRule->setEndDate(new \DateTime('2017-10-05T17:35:26+02:00'));
        $closingRule->setRestaurant($restaurant);
        $restaurant->getClosingRules()->add($closingRule);

        $date = new \DateTime('2017-10-04T17:30:26+02:00');


        $availabilities = $restaurant->getAvailabilities($date);

        $this->assertEquals([
            '2017-10-04T18:30:00+02:00',
            '2017-10-05T17:45:00+02:00',
            '2017-10-05T18:00:00+02:00',
            '2017-10-05T18:15:00+02:00',
            '2017-10-05T18:30:00+02:00',
            '2017-10-05T18:45:00+02:00',
            '2017-10-05T19:00:00+02:00'
        ], $availabilities);
    }
}
