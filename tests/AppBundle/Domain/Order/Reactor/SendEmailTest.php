<?php

namespace Tests\AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Reactor\SendEmail;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\User;
use AppBundle\Entity\Vendor;
use AppBundle\Entity\Sylius\Customer;
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
use SimpleBus\Message\Bus\MessageBus;

class SendEmailTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->emailManager = $this->prophesize(EmailManager::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->eventBus = $this->prophesize(MessageBus::class);
        $this->localBusinessRepository = $this->prophesize(LocalBusinessRepository::class);

        $this->sendEmail = new SendEmail(
            $this->emailManager->reveal(),
            $this->settingsManager->reveal(),
            $this->eventBus->reveal(),
            $this->localBusinessRepository->reveal()
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
            ->isFoodtech()
            ->willReturn(true);
        $order
            ->getCustomer()
            ->willReturn($customer->reveal());
        $order
            ->getVendor()
            ->willReturn(Vendor::withRestaurant($restaurant->reveal()));

        $this->emailManager
            ->createOrderCreatedMessageForCustomer($order->reveal())
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->createCovid19Message()
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->createOrderCreatedMessageForAdmin($order->reveal())
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->createOrderCreatedMessageForOwner($order->reveal(), Argument::type(LocalBusiness::class))
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->sendTo(Argument::type(\Swift_Message::class), Argument::any())
            ->shouldBeCalledTimes(4);

        call_user_func_array($this->sendEmail, [ new Event\OrderCreated($order->reveal()) ]);
    }

    public function testOrderCreatedWithHub()
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
        $hub->addRestaurant($restaurant1->reveal());
        $hub->addRestaurant($restaurant2->reveal());

        $product1 = $this->prophesize(ProductInterface::class);
        $product2 = $this->prophesize(ProductInterface::class);

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->isFoodtech()
            ->willReturn(true);
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

        $this->localBusinessRepository
            ->findOneByProduct($product1->reveal())
            ->willReturn($restaurant1->reveal());

        $this->localBusinessRepository
            ->findOneByProduct($product2->reveal())
            ->willReturn($restaurant2->reveal());

        $this->emailManager
            ->createOrderCreatedMessageForCustomer($order->reveal())
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->createCovid19Message()
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->createOrderCreatedMessageForAdmin($order->reveal())
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->createOrderCreatedMessageForOwner($order->reveal(), Argument::type(LocalBusiness::class))
            ->willReturn(new \Swift_Message());

        $this->emailManager
            ->sendTo(Argument::type(\Swift_Message::class), Argument::any())
            ->shouldBeCalledTimes(5);

        call_user_func_array($this->sendEmail, [ new Event\OrderCreated($order->reveal()) ]);
    }
}
