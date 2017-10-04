<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Utils\CartItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;

class OrderTest extends TestCase
{
    private $validator;

    public function setUp()
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();
    }

    private function violationsToArray(ConstraintViolationList $errors)
    {
        return array_map(function (ConstraintViolation $violation) {
            return $violation->getPropertyPath();
        }, $errors->getIterator()->getArrayCopy());
    }

    public function testAddCartItem()
    {
        $order = new Order();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new MenuItem();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $pizzaItem = new CartItem($pizza, 4);
        $order->addCartItem($pizzaItem, $pizza);

        $saladItem = new CartItem($salad, 2);
        $order->addCartItem($saladItem, $salad);

        $this->assertCount(2, $order->getOrderedItem());

        $pizzaItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($pizza) {
            return $orderItem->getMenuItem() === $pizza;
        })->first();

        $saladItem = $order->getOrderedItem()->filter(function (OrderItem $orderItem) use ($salad) {
            return $orderItem->getMenuItem() === $salad;
        })->first();


        $this->assertEquals(4, $pizzaItem->getQuantity());
        $this->assertEquals(2, $saladItem->getQuantity());
    }

    public function testTotal()
    {
        $order = new Order();

        $pizza = new MenuItem();
        $pizza
            ->setName('Pizza')
            ->setPrice(10);

        $salad = new MenuItem();
        $salad
            ->setName('Salad')
            ->setPrice(5);

        $pizzaItem = new CartItem($pizza, 4);
        $order->addCartItem($pizzaItem, $pizza);

        $saladItem = new CartItem($salad, 2);
        $order->addCartItem($saladItem, $salad);

        $this->assertEquals(50, $order->getTotal());
    }

    public function testDistanceValidation()
    {
        $restaurant = new Restaurant();
        $restaurant->setMaxDistance(3000);
        $restaurant->addOpeningHour('Mo-Sa 11:30-14:30');

        $delivery = new Delivery();

        $order = new Order();
        $order->setDelivery($delivery);
        $order->setRestaurant($restaurant);

        $delivery->setDate(new \DateTime('2017-09-02 12:30:00'));

        // With "Default" group,
        // delivery.distance & delivery.duration are optional
        $errors = $this->validator->validate($order);
        $this->assertEquals(0, count($errors));

        // With "Order" group,
        // delivery.distance & delivery.duration are mandatory
        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertContains('delivery.distance', $this->violationsToArray($errors));

        // Order is valid
        $delivery->setDuration(30);
        $delivery->setDistance(1500);

        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertEquals(0, count($errors));
    }

    public function testDateValidation()
    {
        $restaurant = new Restaurant();
        $restaurant->setMaxDistance(3000);
        $restaurant->addOpeningHour('Mo-Sa 11:30-14:30');

        $delivery = new Delivery();

        $order = new Order();
        $order->setDelivery($delivery);
        $order->setRestaurant($restaurant);

        $delivery->setDuration(30);
        $delivery->setDistance(1500);

        // Restaurant is open
        $delivery->setDate(new \DateTime('2017-09-02 12:30:00'));
        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertEquals(0, count($errors));

        // Restaurant is closed
        $delivery->setDate(new \DateTime('2017-09-03 12:30:00'));
        $errors = $this->validator->validate($order, null, ['order']);
        $this->assertContains('delivery.date', $this->violationsToArray($errors));
    }
}
