<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant\PreparationTimeRule;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\PreparationTimeCalculator;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class PreparationTimeCalculatorTest extends TestCase
{
    use ProphecyTrait;

    private $config;

    public function setUp(): void
    {
        $this->config = [
            'restaurant.state == "rush" and order.itemsTotal < 2000'        => '20 minutes',
            'restaurant.state == "rush" and order.itemsTotal in 2000..5000' => '30 minutes',
            'restaurant.state == "rush" and order.itemsTotal > 5000'        => '45 minutes',
            'order.itemsTotal <= 2000'                                      => '10 minutes',
            'order.itemsTotal in 2000..5000'                                => '15 minutes',
            'order.itemsTotal > 5000'                                       => '30 minutes',
        ];
    }

    private function createEmptyOrder()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection([]));

        $order
            ->getItemsTotal()
            ->willReturn(0);

        return $order->reveal();
    }

    private function createOrder($total, $state = 'normal', array $customConfig = [])
    {
        $restaurant = new LocalBusiness();
        $restaurant->setState($state);

        foreach ($customConfig as $expr => $time) {
            $rule = new PreparationTimeRule();
            $rule->setExpression($expr);
            $rule->setTime($time);

            $restaurant->addPreparationTimeRule($rule);
        }

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection([ $restaurant ]));

        $order
            ->getItemsTotal()
            ->willReturn($total);

        return $order->reveal();
    }

    private function createOrderWithHub($total, $config = [])
    {
        $restaurants = [];
        foreach ($config as $c) {

            [ $state, $rules ] = $c;

            $restaurant = new LocalBusiness();
            $restaurant->setState($state);

            foreach ($rules as $expr => $time) {
                $rule = new PreparationTimeRule();
                $rule->setExpression($expr);
                $rule->setTime($time);

                $restaurant->addPreparationTimeRule($rule);
            }

            $restaurants[] = $restaurant;
        }

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection($restaurants));

        $order
            ->getItemsTotal()
            ->willReturn($total);

        return $order->reveal();
    }

    public function calculateProvider()
    {
        return [
            // default value when empty
            [
                $this->createEmptyOrder(),
                '10 minutes',
            ],
            // state = normal
            [
                $this->createOrder(1500),
                '10 minutes',
            ],
            [
                $this->createOrder(3000),
                '15 minutes',
            ],
            [
                $this->createOrder(6000),
                '30 minutes',
            ],
            // state = rush
            [
                $this->createOrder(1500, 'rush'),
                '20 minutes',
            ],
            [
                $this->createOrder(3000, 'rush'),
                '30 minutes',
            ],
            [
                $this->createOrder(6000, 'rush'),
                '45 minutes',
            ],
            // custom config
            [
                $this->createOrder(1500, 'normal', ['order.itemsTotal > 0' => '70 minutes']),
                '70 minutes',
            ],
            // hubs
            [
                $this->createOrderWithHub(1500, [
                    [ 'normal', ['order.itemsTotal > 0' => '10 minutes'] ],
                    [ 'normal', ['order.itemsTotal > 0' => '40 minutes'] ],
                    [ 'normal', ['order.itemsTotal > 0' => '30 minutes'] ],
                ]),
                '40 minutes',
            ],
        ];
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculate(
        OrderInterface $order,
        $expectedPrepatationTime)
    {
        $this->calculator = new PreparationTimeCalculator($this->config);
        $this->assertEquals($expectedPrepatationTime, $this->calculator->calculate($order));
    }
}
