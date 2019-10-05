<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DeliveryTest extends TestCase
{
    public function testNewDeliveryHasTwoTasks()
    {
        $delivery = new Delivery();

        $this->assertNotNull($delivery->getPickup());
        $this->assertNotNull($delivery->getDropoff());
        $this->assertCount(2, $delivery->getTasks());
    }

    public function testAddTaskThrowsException()
    {
        $this->expectException(\RuntimeException::class);

        $delivery = new Delivery();

        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);

        $delivery->addTask($task);
    }

    public function testToExpressionLanguageValues()
    {
        $pickupAddress = new Address();
        $pickupAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $delivery = new Delivery();
        $delivery->setDistance(2500);
        $delivery->getPickup()->setAddress($pickupAddress);
        $delivery->getDropoff()->setAddress($dropoffAddress);

        $values = Delivery::toExpressionLanguageValues($delivery);

        $this->assertArrayHasKey('distance', $values);
        $this->assertArrayHasKey('weight', $values);
        $this->assertArrayHasKey('vehicle', $values);
        $this->assertArrayHasKey('pickup', $values);
        $this->assertArrayHasKey('dropoff', $values);

        $language = new ExpressionLanguage();

        $this->assertEquals($pickupAddress, $language->evaluate('pickup.address', $values));
        $this->assertEquals($dropoffAddress, $language->evaluate('dropoff.address', $values));
    }
}
