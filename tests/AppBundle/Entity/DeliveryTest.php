<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use PHPUnit\Framework\TestCase;

class DeliveryTest extends TestCase
{
    private $address;
    private $contract;

    public function setUp()
    {
        $this->address = new Address();
        $this->address->setStreetAddress('1, rue de la Paix');
        $this->address->setPostalCode('75000');
        $this->address->setAddressLocality('Paris');

        $this->contract = new Contract();
        $this->contract->setFlatDeliveryPrice(03.50);
    }

    public function testConstructor()
    {
        $restaurant = new Restaurant();
        $restaurant->setContract($this->contract);
        $restaurant->setAddress($this->address);

        $order = new Order();
        $order->setRestaurant($restaurant);

        $delivery = new Delivery($order);

        $this->assertEquals(03.50, $delivery->getPrice());
        $this->assertSame($this->address, $delivery->getOriginAddress());
    }

    public function testSetOrder()
    {
        $restaurant = new Restaurant();
        $restaurant->setContract($this->contract);
        $restaurant->setAddress($this->address);

        $order = new Order();
        $order->setRestaurant($restaurant);

        $delivery = new Delivery();
        $delivery->setOrder($order);

        $this->assertEquals(03.50, $delivery->getPrice());
        $this->assertSame($this->address, $delivery->getOriginAddress());
    }
}
