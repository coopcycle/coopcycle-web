<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\EnhanceShippingAddress;
use AppBundle\Entity\Address;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\OrderInterface;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;

class EnhanceShippingAddressTest extends TestCase
{
    use ProphecyTrait;

    private $reactor;

    public function setUp(): void
    {
        $this->reactor = new EnhanceShippingAddress(
            PhoneNumberUtil::getInstance()
        );
    }

    public function testCopiesDataFromCustomer()
    {
        $order = $this->prophesize(Order::class);
        $order
            ->getDelivery()
            ->willReturn(null);

        $order
            ->hasVendor()
            ->willReturn(true);

        $order
            ->isTakeaway()
            ->willReturn(false);

        $shippingAddress = new Address();

        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $customer = $this->prophesize(Customer::class);

        $customer
            ->getFullName()
            ->willReturn('John Doe');
        $customer
            ->getPhoneNumber()
            ->willReturn('+33612345678');

        $order
            ->getCustomer()
            ->willReturn($customer->reveal());

        call_user_func_array($this->reactor, [ new Event\OrderCreated($order->reveal()) ]);

        $this->assertNotNull($shippingAddress->getTelephone());
        $this->assertInstanceOf(PhoneNumber::class, $shippingAddress->getTelephone());

        $this->assertNotNull($shippingAddress->getContactName());
        $this->assertEquals('John Doe', $shippingAddress->getContactName());
    }

    public function testCopiesDataFromAddress()
    {
        $order = $this->prophesize(Order::class);
        $order
            ->getDelivery()
            ->willReturn(null);

        $order
            ->hasVendor()
            ->willReturn(true);

        $order
            ->isTakeaway()
            ->willReturn(false);

        $shippingAddress = new Address();
        $shippingAddress->setTelephone(
            PhoneNumberUtil::getInstance()->parse('+33612345678')
        );

        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $customer = new Customer();
        $customer->setFullName('John Doe');

        $order
            ->getCustomer()
            ->willReturn($customer);

        call_user_func_array($this->reactor, [ new Event\OrderCreated($order->reveal()) ]);

        $this->assertNotNull($customer->getPhoneNumber());
        $this->assertEquals('+33612345678', $customer->getPhoneNumber());
    }

    public function testDoesNotUpdateCustomerPhoneNumber()
    {
        $order = $this->prophesize(Order::class);
        $order
            ->getDelivery()
            ->willReturn(null);

        $order
            ->hasVendor()
            ->willReturn(true);

        $order
            ->isTakeaway()
            ->willReturn(false);

        $shippingAddress = new Address();
        $shippingAddress->setTelephone(
            PhoneNumberUtil::getInstance()->parse('+33687654321')
        );

        $order
            ->getShippingAddress()
            ->willReturn($shippingAddress);

        $customer = new Customer();

        $customer->setFullName('John Doe');
        $customer->setPhoneNumber('+33612345678');

        $order
            ->getCustomer()
            ->willReturn($customer);

        call_user_func_array($this->reactor, [ new Event\OrderCreated($order->reveal()) ]);

        $this->assertEquals('+33612345678', $customer->getPhoneNumber());
    }
}
