<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Reactor\GrabLoopEats;
use AppBundle\Entity\User;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;

class GrabLoopEatsTest extends TestCase
{
    use ProphecyTrait;

    private $grabLoopEats;

    public function setUp(): void
    {
        $this->loopeat = $this->prophesize(LoopEatClient::class);

        $this->grabLoopEats = new GrabLoopEats(
            $this->loopeat->reveal()
        );
    }

    public function testWithoutRestaurant()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn(null);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testWithLoopEatDisabled()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setLoopeatEnabled(false);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testWithReusablePackagingNotEnabled()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(false);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testWithReusablePackagingQuantityEqualToZero()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setLoopeatEnabled(true);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(0);

        $this->loopeat
            ->grab(Argument::any(), Argument::any(), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testGrab()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setLoopeatEnabled(true);

        $user = new User();

        $customer = new Customer();
        $customer->setUser($user);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(2);
        $order
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);
        $order
            ->getCustomer()
            ->willReturn($customer);

        $this->loopeat
            ->return($customer, 0)
            ->willReturn(true)
            ->shouldBeCalled();

        $this->loopeat
            ->grab($customer, $restaurant, 2)
            ->willReturn(true)
            ->shouldBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testGrabWithGuestCheckout()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setLoopeatEnabled(true);

        $customer = $this->prophesize(Customer::class);
        $customer->hasUser()->willReturn(false);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(2);
        $order
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);
        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        $this->loopeat
            ->return($customer->reveal(), 0)
            ->willReturn(true)
            ->shouldBeCalled();

        $this->loopeat
            ->grab($customer->reveal(), $restaurant, 2)
            ->willReturn(true)
            ->shouldBeCalled();

        $customer->clearLoopEatCredentials()->shouldBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }

    public function testDoNotGrabIfReturnFails()
    {
        $restaurant = new LocalBusiness();
        $restaurant->setLoopeatEnabled(true);

        $user = new User();

        $customer = new Customer();
        $customer->setUser($user);

        $order = $this->prophesize(Order::class);
        $order
            ->getRestaurant()
            ->willReturn($restaurant);
        $order
            ->isReusablePackagingEnabled()
            ->willReturn(true);
        $order
            ->getReusablePackagingQuantity()
            ->willReturn(2);
        $order
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);
        $order
            ->getCustomer()
            ->willReturn($customer);

        $this->loopeat
            ->return($customer, 0)
            ->willReturn(false)
            ->shouldBeCalled();

        $this->loopeat
            ->grab(Argument::type(Customer::class), Argument::type(LocalBusiness::class), Argument::type('int'))
            ->shouldNotBeCalled();

        call_user_func_array($this->grabLoopEats, [ new OrderPicked($order->reveal()) ]);
    }
}
