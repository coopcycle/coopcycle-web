<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\SendEmail;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\User;
use AppBundle\Entity\Vendor;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Message\OrderReceiptEmail;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;
use Symfony\Component\Mime\Email;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SendEmailTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->emailManager = $this->prophesize(EmailManager::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->eventBus = $this->prophesize(MessageBus::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);

        $this->settingsManager->get('administrator_email')->willReturn('admin@acme.com');

        $this->messageBus
            ->dispatch(Argument::type(OrderReceiptEmail::class))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $this->sendEmail = new SendEmail(
            $this->emailManager->reveal(),
            $this->settingsManager->reveal(),
            $this->eventBus->reveal(),
            $this->messageBus->reveal()
        );
    }

    private function createOrderItem(ProductInterface $product)
    {
        $item = $this->prophesize(OrderItemInterface::class);

        $variant = $this->prophesize(ProductVariantInterface::class);

        $variant->getProduct()->willReturn($product);

        $item->getVariant()->willReturn($variant->reveal());

        return $item->reveal();
    }

    public function testOrderCreatedWithRestaurant()
    {
        $customer = $this->prophesize(Customer::class);

        $customer->getEmail()->willReturn('john@example.com');
        $customer->getFullName()->willReturn('John Doe');

        $bob = $this->prophesize(User::class);
        $jane = $this->prophesize(User::class);

        $bob->getEmail()->willReturn('bob@example.com');
        $bob->getFullName()->willReturn('Bob');

        $jane->getEmail()->willReturn('jane@example.com');
        $jane->getFullName()->willReturn('Jane');

        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant->getOwners()->willReturn(new ArrayCollection([
            $bob->reveal(),
            $jane->reveal()
        ]));

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(false);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection([
                $restaurant->reveal()
            ]));
        $order
            ->getCustomer()
            ->willReturn($customer->reveal());
        $order
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant->reveal()));

        $this->emailManager
            ->createOrderCreatedMessageForCustomer($order->reveal())
            ->willReturn(new Email());

        $this->emailManager
            ->createOrderCreatedMessageForAdmin($order->reveal())
            ->willReturn(new Email());

        $this->emailManager
            ->createOrderCreatedMessageForOwner($order->reveal(), Argument::type(LocalBusiness::class))
            ->willReturn(new Email());

        $this->emailManager
            ->sendTo(Argument::type(Email::class), Argument::any(), Argument::any())
            ->shouldBeCalledTimes(3);

        $this->eventBus
            ->handle(Argument::that(function (Event\EmailSent $event) {

                $payload = $event->toPayload();

                $emails = [
                    'john@example.com',
                    'bob@example.com',
                    'jane@example.com',
                    'admin@acme.com',
                ];

                return isset($payload['recipient']) && in_array($payload['recipient'], $emails);
            }))
            ->shouldBeCalled(4);

        call_user_func_array($this->sendEmail, [ new Event\OrderCreated($order->reveal()) ]);
    }

    private function createHubOrder()
    {
        $customer = $this->prophesize(Customer::class);

        $customer->getEmail()->willReturn('john@example.com');
        $customer->getFullName()->willReturn('John Doe');

        $bob = $this->prophesize(User::class);
        $jane = $this->prophesize(User::class);

        $bob->getEmail()->willReturn('bob@example.com');
        $bob->getFullName()->willReturn('Bob');

        $jane->getEmail()->willReturn('jane@example.com');
        $jane->getFullName()->willReturn('Jane');

        $restaurant1 = $this->prophesize(LocalBusiness::class);
        $restaurant2 = $this->prophesize(LocalBusiness::class);

        $restaurant1->getOwners()->willReturn(new ArrayCollection([ $bob->reveal() ]));
        $restaurant2->getOwners()->willReturn(new ArrayCollection([ $jane->reveal() ]));

        $hub = new Hub();

        $product1 = $this->prophesize(ProductInterface::class);
        $product2 = $this->prophesize(ProductInterface::class);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->isMultiVendor()
            ->willReturn(true);
        $order
            ->getRestaurants()
            ->willReturn(new ArrayCollection([
                $restaurant1->reveal(),
                $restaurant2->reveal()
            ]));
        $order
            ->getCustomer()
            ->willReturn($customer->reveal());
        $order
            ->getVendor()
            ->willReturn(Vendor::withHub($hub));
        $order
            ->getItems()
            ->willReturn(new ArrayCollection([
                $this->createOrderItem($product1->reveal()),
                $this->createOrderItem($product2->reveal()),
            ]));

        return $order;
    }

    public function testOrderCreatedWithHub()
    {
        $order = $this->createHubOrder();

        $this->emailManager
            ->createOrderCreatedMessageForCustomer($order->reveal())
            ->willReturn(new Email());

        $this->emailManager
            ->createOrderCreatedMessageForAdmin($order->reveal())
            ->willReturn(new Email());

        $this->emailManager
            ->createOrderCreatedMessageForOwner($order->reveal(), Argument::type(LocalBusiness::class))
            ->willReturn(new Email());

        $this->emailManager
            ->sendTo(Argument::type(Email::class), Argument::any())
            ->shouldBeCalledTimes(2);

        $this->eventBus
            ->handle(Argument::that(function (Event\EmailSent $event) {

                $payload = $event->toPayload();

                $emails = [
                    'bob@example.com',
                    'jane@example.com',
                ];

                // Make sure the email is *NOT* sent to vendors
                // when it is a multi vendor order
                return isset($payload['recipient']) && !in_array($payload['recipient'], $emails);
            }))
            ->shouldBeCalled(4);

        call_user_func_array($this->sendEmail, [ new Event\OrderCreated($order->reveal()) ]);
    }

    public function testOrderAcceptedWithHub()
    {
        $order = $this->createHubOrder();

        $this->emailManager
            ->createOrderAcceptedMessage($order->reveal())
            ->willReturn(new Email());

        $this->emailManager
            ->createOrderCreatedMessageForOwner($order->reveal(), Argument::type(LocalBusiness::class))
            ->willReturn(new Email())
            ->shouldBeCalledTimes(2);

        $this->emailManager
            ->sendTo(Argument::type(Email::class), Argument::any())
            ->shouldBeCalledTimes(3);

        $this->eventBus
            ->handle(Argument::that(function (Event\EmailSent $event) {

                $payload = $event->toPayload();

                $emails = [
                    'john@example.com',
                    'bob@example.com',
                    'jane@example.com',
                    'admin@acme.com',
                ];

                return isset($payload['recipient']) && in_array($payload['recipient'], $emails);
            }))
            ->shouldBeCalled(4);

        call_user_func_array($this->sendEmail, [ new Event\OrderAccepted($order->reveal()) ]);
    }

    public function testOrderFulfilled()
    {
        $customer = $this->prophesize(Customer::class);

        $customer->getEmail()->willReturn('john@example.com');
        $customer->getFullName()->willReturn('John Doe');

        $restaurant = $this->prophesize(LocalBusiness::class);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->hasVendor()
            ->willReturn(true);
        $order
            ->getNumber()
            ->willReturn('ABC123');

        call_user_func_array($this->sendEmail, [ new Event\OrderFulfilled($order->reveal()) ]);

        $this
            ->messageBus
            ->dispatch(new OrderReceiptEmail('ABC123'))
            ->shouldHaveBeenCalled();
    }
}
