<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\ExpressionLanguage\PackagesResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DeliveryTest extends TestCase
{
    use ProphecyTrait;

    public function testNewDeliveryHasTwoTasks()
    {
        $delivery = new Delivery();

        $this->assertNotNull($delivery->getPickup());
        $this->assertNotNull($delivery->getDropoff());
        $this->assertCount(2, $delivery->getTasks());
    }

    public function testToExpressionLanguageValues()
    {
        $pickupAddress = new Address();
        $pickupAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $smallPackage = new Package();
        $smallPackage->setName('S');

        $mediumPackage = new Package();
        $mediumPackage->setName('M');

        $delivery = new Delivery();
        $delivery->setDistance(2500);
        $delivery->getPickup()->setAddress($pickupAddress);
        $delivery->getDropoff()->setAddress($dropoffAddress);
        $delivery->addPackageWithQuantity($smallPackage, 1);
        $delivery->addPackageWithQuantity($mediumPackage, 2);
        $delivery->getDropoff()->setDoorstep(true);

        $values = Delivery::toExpressionLanguageValues($delivery);

        $this->assertArrayHasKey('distance', $values);
        $this->assertArrayHasKey('weight', $values);
        $this->assertArrayHasKey('vehicle', $values);
        $this->assertArrayHasKey('pickup', $values);
        $this->assertArrayHasKey('dropoff', $values);
        $this->assertArrayHasKey('packages', $values);

        $language = new ExpressionLanguage();

        $this->assertEquals($pickupAddress, $language->evaluate('pickup.address', $values));
        $this->assertEquals($dropoffAddress, $language->evaluate('dropoff.address', $values));
        $this->assertTrue($language->evaluate('dropoff.doorstep', $values));

        $this->assertInstanceOf(PackagesResolver::class, $language->evaluate('packages', $values));
        $this->assertEquals(1, $language->evaluate('packages.quantity("S")', $values));
        $this->assertEquals(2, $language->evaluate('packages.quantity("M")', $values));
        $this->assertEquals(0, $language->evaluate('packages.quantity("XL")', $values));
    }

    public function testAddPackageWithQuantity()
    {
        $delivery = new Delivery();

        $smallPackage = new Package();
        $smallPackage->setName('S');

        $mediumPackage = new Package();
        $mediumPackage->setName('M');

        $delivery->addPackageWithQuantity($smallPackage, 1);
        $this->assertEquals(1, $delivery->getQuantityForPackage($smallPackage));

        $delivery->addPackageWithQuantity($smallPackage, 1);
        $this->assertEquals(2, $delivery->getQuantityForPackage($smallPackage));

        $delivery->addPackageWithQuantity($mediumPackage, 0);
        $this->assertEquals(0, $delivery->getQuantityForPackage($mediumPackage));
    }

    public function testAddPackageWithQuantityWithMultipleDropoffs()
    {
        $delivery = new Delivery();

        $otherDrop = new Task();
        $otherDrop->setType(Task::TYPE_DROPOFF);

        $delivery->addTask($otherDrop);

        $this->assertCount(3, $delivery->getTasks());

        $smallPackage = new Package();
        $smallPackage->setName('S');

        $mediumPackage = new Package();
        $mediumPackage->setName('M');

        $delivery->addPackageWithQuantity($smallPackage, 1);

        $i = 0;
        foreach ($delivery->getTasks() as $task) {
            if ($task->isDropoff()) {
                $this->assertCount(0 === $i ? 1 : 0, $task->getPackages());
                $this->assertEquals(0 === $i ? 1 : 0, $task->getQuantityForPackage($smallPackage));
                $i++;
            }
        }

        $delivery->addPackageWithQuantity($smallPackage, 1);

        $i = 0;
        foreach ($delivery->getTasks() as $task) {
            if ($task->isDropoff()) {
                $this->assertCount(0 === $i ? 1 : 0, $task->getPackages());
                $this->assertEquals(0 === $i ? 2 : 0, $task->getQuantityForPackage($smallPackage));
                $i++;
            }
        }

        $delivery->addPackageWithQuantity($mediumPackage, 3);

        $i = 0;
        foreach ($delivery->getTasks() as $task) {
            if ($task->isDropoff()) {
                $this->assertCount(0 === $i ? 2 : 0, $task->getPackages());
                $this->assertEquals(0 === $i ? 3 : 0, $task->getQuantityForPackage($mediumPackage));
                $i++;
            }
        }
    }

    public function testToExpressionLanguageValuesWithOrder()
    {
        $pickupAddress = new Address();
        $pickupAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $smallPackage = new Package();
        $smallPackage->setName('S');

        $mediumPackage = new Package();
        $mediumPackage->setName('M');

        $delivery = new Delivery();
        $delivery->setDistance(2500);
        $delivery->getPickup()->setAddress($pickupAddress);
        $delivery->getDropoff()->setAddress($dropoffAddress);
        $delivery->addPackageWithQuantity($smallPackage, 1);
        $delivery->addPackageWithQuantity($mediumPackage, 2);
        $delivery->getDropoff()->setDoorstep(true);

        $language = new ExpressionLanguage();

        $values = Delivery::toExpressionLanguageValues($delivery);

        $this->assertArrayHasKey('order', $values);
        $this->assertNotNull($values['order']);

        $this->assertEquals(0, $language->evaluate('order.itemsTotal', $values));

        $order = $this->prophesize(Order::class);
        $order->getItemsTotal()->willReturn(3000);

        $delivery->setOrder($order->reveal());

        $values = Delivery::toExpressionLanguageValues($delivery);

        $this->assertNotNull($values['order']);

        $this->assertEquals(3000, $language->evaluate('order.itemsTotal', $values));
    }

    public function testCreateWithTasksWith2Params()
    {
        $pickupAddress = new Address();
        $pickupAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $pickup = new Task();
        $pickup->setAddress($pickupAddress);
        $pickup->setBefore(new \DateTime('today 12:00'));

        $dropoff = new Task();
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setBefore(new \DateTime('today 12:00'));

        $delivery = Delivery::createWithTasks($pickup, $dropoff);

        $this->assertCount(2, $delivery->getTasks());
        $this->assertSame($pickup, $delivery->getPickup());
        $this->assertSame($dropoff, $delivery->getDropoff());

        $this->assertSame($dropoff, $delivery->getPickup()->getNext());
        $this->assertSame($pickup, $delivery->getDropoff()->getPrevious());

        $this->assertSame($delivery, $pickup->getDelivery());
        $this->assertSame($delivery, $dropoff->getDelivery());
    }

    public function testCreateWithTasksWith4Params()
    {
        $addresses = [];
        for ($i = 0; $i < 4; $i++) {
            $address = new Address();
            $address->setGeo(new GeoCoordinates(48.842049, 2.331181));
            $addresses[] = $address;
        }

        $tasks = [];

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAddress($addresses[0]);
        $pickup->setBefore(new \DateTime('today 12:00'));

        $tasks[] = $pickup;

        for ($i = 1; $i < 4; $i++) {
            $dropoff = new Task();
            $dropoff->setAddress($addresses[$i]);
            $dropoff->setBefore(new \DateTime('today 12:00'));

            $tasks[] = $dropoff;
        }

        $delivery = Delivery::createWithTasks(...$tasks);

        $this->assertCount(4, $delivery->getTasks());
        $this->assertSame($pickup, $delivery->getPickup());
        // Delivery::getDropoff() returns the *LAST* dropoff to stay BC
        $this->assertSame($tasks[3], $delivery->getDropoff());

        // Only dropoffs should have link with pickup
        $this->assertSame($tasks[0], $tasks[1]->getPrevious());
        $this->assertSame($tasks[0], $tasks[2]->getPrevious());
        $this->assertSame($tasks[0], $tasks[3]->getPrevious());

        $this->assertNull($tasks[0]->getNext());

        foreach ($tasks as $task) {
            $this->assertSame($delivery, $task->getDelivery());
        }
    }

    public function testToExpressionLanguageValuesWithMultipleDropoffs()
    {
        $pickupAddress = new Address();
        $pickupAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $dropoffAddress = new Address();
        $dropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $otherDropoffAddress = new Address();
        $otherDropoffAddress->setGeo(new GeoCoordinates(48.842049, 2.331181));

        $delivery = new Delivery();
        $delivery->setDistance(2500);
        $delivery->getPickup()->setAddress($pickupAddress);
        $delivery->getDropoff()->setAddress($dropoffAddress);
        $delivery->getDropoff()->setDoorstep(true);

        $otherDrop = new Task();
        $otherDrop->setAddress($otherDropoffAddress);

        $delivery->addTask($otherDrop);

        $values = Delivery::toExpressionLanguageValues($delivery);

        $this->assertArrayHasKey('distance', $values);
        $this->assertArrayHasKey('weight', $values);
        $this->assertArrayHasKey('vehicle', $values);
        $this->assertArrayHasKey('pickup', $values);
        $this->assertArrayHasKey('dropoff', $values);

        $language = new ExpressionLanguage();

        $this->assertEquals($pickupAddress, $language->evaluate('pickup.address', $values));
        $this->assertEquals($otherDropoffAddress, $language->evaluate('dropoff.address', $values));
        $this->assertFalse($language->evaluate('dropoff.doorstep', $values));
    }

    public function testGetPackages()
    {
        $delivery = new Delivery();

        $smallPackage = new Package();
        $smallPackage->setName('S');

        $delivery->getDropoff()->addPackageWithQuantity($smallPackage, 2);

        $packages = $delivery->getPackages();

        $this->assertCount(1, $packages);
        $this->assertEquals(2, $delivery->getQuantityForPackage($smallPackage));
    }

    public function testGetPackagesWithMultipleDropoffs()
    {
        $delivery = new Delivery();

        $otherDrop = new Task();
        $otherDrop->setType(Task::TYPE_DROPOFF);

        $delivery->addTask($otherDrop);

        $smallPackage = new Package();
        $smallPackage->setName('S');

        $i = 0;
        foreach ($delivery->getTasks() as $task) {
            if ($task->isDropoff()) {
                if (0 === $i) {
                    $task->addPackageWithQuantity($smallPackage, 2);
                    $this->assertCount(1, $delivery->getPackages());
                    $this->assertEquals(2, $delivery->getQuantityForPackage($smallPackage));
                }
                if (1 === $i) {
                    $task->addPackageWithQuantity($smallPackage, 1);
                    $this->assertCount(1, $delivery->getPackages());
                    $this->assertEquals(3, $delivery->getQuantityForPackage($smallPackage));
                }
                $i++;
            }
        }
    }

    public function testWithTasks()
    {
        $delivery = new Delivery();

        $pickup = new Task();
        // Even if the first task is not a pickup,
        // it will be considered as a pickup
        // $pickup->setType(Task::TYPE_PICKUP);

        $dropoff1 = new Task();
        $dropoff1->setType(Task::TYPE_DROPOFF);

        $dropoff2 = new Task();
        $dropoff2->setType(Task::TYPE_DROPOFF);

        $delivery = $delivery->withTasks(...[ $pickup, $dropoff1, $dropoff2 ]);

        $this->assertNotNull($delivery->getPickup());
    }

    public function testGetTasksWithFilterExpression()
    {
        $delivery = new Delivery();

        $firstTask = $delivery->getItems()->get(0)->getTask();
        $firstTask->setStatus(Task::STATUS_CANCELLED);

        $notCancelled = $delivery->getTasks('not task.isCancelled()');

        $this->assertCount(1, $notCancelled);
        $this->assertNotContains($firstTask, $notCancelled);

        $cancelled = $delivery->getTasks('task.isCancelled()');

        $this->assertCount(1, $cancelled);
        $this->assertContains($firstTask, $cancelled);
    }
}
