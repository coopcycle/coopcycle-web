<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class OrderTimeHelperTest extends TestCase
{
    use ProphecyTrait;

    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    public function setUp(): void
    {
        $this->preparationTimeCalculator = $this->prophesize(PreparationTimeCalculator::class);
        $this->shippingDateFilter = $this->prophesize(ShippingDateFilter::class);
        $this->shippingTimeCalculator = $this->prophesize(ShippingTimeCalculator::class);

        $this->helper = new OrderTimeHelper(
            $this->shippingDateFilter->reveal(),
            $this->preparationTimeCalculator->reveal(),
            $this->shippingTimeCalculator->reveal(),
            'fr'
        );
    }

    private function createOrder($total, $shippedAt)
    {
        $restaurant = new Restaurant();
        $restaurant->setState($state);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->getItemsTotal()
            ->willReturn($total);
        $order
            ->getShippedAt()
            ->willReturn(new \DateTime($shippedAt));

        return $order->reveal();
    }

    public function testAsapWithSameDayShippingChoices()
    {
        $restaurant = $this->prophesize(Restaurant::class);

        $sameDayChoices = [
            '2020-03-31T14:30:00+02:00',
            '2020-03-31T14:45:00+02:00',
        ];

        $sameDayAndNextDayChoices = [
            // Same day
            '2020-03-31T14:30:00+02:00',
            '2020-03-31T14:45:00+02:00',
            // Next day
            '2020-04-01T13:00:00+02:00',
            '2020-04-01T13:15:00+02:00',
            '2020-04-01T13:30:00+02:00',
            '2020-04-01T13:45:00+02:00',
            '2020-04-01T14:00:00+02:00',
            '2020-04-01T14:15:00+02:00',
            '2020-04-01T14:30:00+02:00',
            '2020-04-01T14:45:00+02:00',
        ];

        $restaurant
            ->getAvailabilities()
            ->willReturn(
                // Mock multiple method calls
                $sameDayChoices,
                $sameDayAndNextDayChoices
            );

        $cart = $this->prophesize(OrderInterface::class);
        $cart
            ->getRestaurant()
            ->willReturn($restaurant->reveal());

        $restaurant
            ->getShippingOptionsDays()
            ->willReturn(1);
        $restaurant
            ->getOpeningHoursBehavior()
            ->willReturn('asap');

        $restaurant
            ->setShippingOptionsDays(2)
            ->shouldBeCalled();

        $restaurant
            ->setShippingOptionsDays(1)
            ->shouldBeCalled();

        $this->shippingDateFilter
            ->accept($cart, Argument::type(\DateTime::class))
            ->will(function ($args) use ($sameDayChoices) {
                if (in_array($args[1]->format(\DateTime::ATOM), $sameDayChoices)) {
                    return false;
                }

                return true;
            });

        $shippingTimeRange = $this->helper->getShippingTimeRange($cart->reveal());

        $this->assertEquals(new \DateTime('2020-04-01 12:55:00'), $shippingTimeRange->getLower());
        $this->assertEquals(new \DateTime('2020-04-01 13:05:00'), $shippingTimeRange->getUpper());
    }
}
