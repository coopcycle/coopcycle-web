<?php

namespace AppBundle\Functional;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class CoursiersBordelaisTest extends TestCase
{
    private $pricingRules = [];

    public function setUp(): void
    {
        $this->pricingRules = [
            self::createPricingRule('vehicle == "bike" and distance in 0..2000', '7.00'),
            self::createPricingRule('vehicle == "bike" and distance in 2000..5000', '9.00'),
            self::createPricingRule('vehicle == "bike" and distance in 5000..8000', '15.00'),
            self::createPricingRule('vehicle == "bike" and distance > 8000', '15.00 + (ceil((distance - 8000) / 1000) * 3.00)'),

            self::createPricingRule('vehicle == "cargo_bike" and distance in 0..2000', '11.00'),
            self::createPricingRule('vehicle == "cargo_bike" and distance in 2000..5000', '16.00'),
            self::createPricingRule('vehicle == "cargo_bike" and distance in 5000..8000', '21.00'),
            self::createPricingRule('vehicle == "cargo_bike" and distance > 8000', '21.00 + (ceil((distance - 8000) / 1000) * 5.00)'),
        ];
    }

    private static function createPricingRule($expression, $price)
    {
        $rule = new PricingRule();
        $rule->setExpression($expression);
        $rule->setPrice($price);

        return $rule;
    }

    private static function createDelivery($vehicle, $distance)
    {
        $delivery = new Delivery();
        $delivery->setVehicle($vehicle);
        $delivery->setDistance($distance);

        return $delivery;
    }

    public function deliveryProvider()
    {
        return [
            [self::createDelivery(Delivery::VEHICLE_BIKE, 5300), 15.00],
            [self::createDelivery(Delivery::VEHICLE_BIKE, 9100), 21.00],
            [self::createDelivery(Delivery::VEHICLE_BIKE, 1100), 7.00],
            [self::createDelivery(Delivery::VEHICLE_BIKE, 2300), 9.00],

            [self::createDelivery(Delivery::VEHICLE_CARGO_BIKE, 5300), 21.00],
            [self::createDelivery(Delivery::VEHICLE_CARGO_BIKE, 9100), 31.00],
            [self::createDelivery(Delivery::VEHICLE_CARGO_BIKE, 1100), 11.00],
            [self::createDelivery(Delivery::VEHICLE_CARGO_BIKE, 2300), 16.00],
        ];
    }

    /**
     * @dataProvider deliveryProvider
     */
    public function testPricingRules(Delivery $delivery, $expectedPrice)
    {
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->addFunction(ExpressionFunction::fromPhp('ceil'));

        foreach ($this->pricingRules as $pricingRule) {
            if ($pricingRule->matches($delivery)) {
                $actualPrice = $pricingRule->evaluatePrice($delivery, $expressionLanguage);
                $this->assertEquals($expectedPrice, $actualPrice);
            }
        }
    }
}
