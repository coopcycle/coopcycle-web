<?php

namespace Tests\AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderPriceUpdated;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Pricing\ManualSupplements;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Message\Order\ProcessOrderAfterTaskCancellation;
use AppBundle\MessageHandler\Order\ProcessOrderAfterTaskCancellationHandler;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ProcessOrderAfterTaskCancellationHandlerTest extends TestCase
{
    use ProphecyTrait;

    private $orderRepository;
    private $eventBus;
    private $deliveryManager;
    private $pricingManager;
    private $entityManager;
    private $logger;
    private $feeCalculationLogger;
    private $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->prophesize(OrderRepository::class);
        $this->eventBus = $this->prophesize(MessageBusInterface::class);
        $this->deliveryManager = $this->prophesize(DeliveryManager::class);
        $this->pricingManager = $this->prophesize(PricingManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->feeCalculationLogger = $this->prophesize(LoggerInterface::class);

        $this->handler = new ProcessOrderAfterTaskCancellationHandler(
            $this->orderRepository->reveal(),
            $this->eventBus->reveal(),
            $this->deliveryManager->reveal(),
            $this->pricingManager->reveal(),
            $this->entityManager->reveal(),
            $this->logger->reveal(),
            $this->feeCalculationLogger->reveal()
        );
    }

    private function buildMessage(int $orderId, bool $recalculatePrice = false): ProcessOrderAfterTaskCancellation
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn($orderId);

        $message = $this->prophesize(ProcessOrderAfterTaskCancellation::class);
        $message->getOrderId()->willReturn($orderId);
        $message->shouldRecalculatePrice()->willReturn($recalculatePrice);

        return $message->reveal();
    }

    public function testOrderNotFoundLogsWarningAndReturnsEarly(): void
    {
        $message = $this->buildMessage(42);

        $this->orderRepository->find(42)->willReturn(null);

        $this->logger->warning(
            'Order not found for processing after task cancellation',
            ['orderId' => 42]
        )->shouldBeCalledOnce();

        $this->deliveryManager->calculateRoute(Argument::any())->shouldNotBeCalled();
        $this->eventBus->dispatch(Argument::any())->shouldNotBeCalled();

        ($this->handler)($message);
    }

    public function testDeliveryNotFoundLogsWarningAndReturnsEarly(): void
    {
        $message = $this->buildMessage(42);

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getDelivery()->willReturn(null);

        $this->orderRepository->find(42)->willReturn($order->reveal());

        $this->logger->warning(
            'Delivery not found for processing after task cancellation',
            ['orderId' => 42]
        )->shouldBeCalledOnce();

        $this->deliveryManager->calculateRoute(Argument::any())->shouldNotBeCalled();
        $this->eventBus->dispatch(Argument::any())->shouldNotBeCalled();

        ($this->handler)($message);
    }

    public function testFoodtechOrderSkipsProcessing(): void
    {
        $message = $this->buildMessage(42);

        $delivery = $this->prophesize(Delivery::class);

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getDelivery()->willReturn($delivery->reveal());
        $order->isFoodtech()->willReturn(true);

        $this->orderRepository->find(42)->willReturn($order->reveal());

        $this->logger->info(
            'Skipping processing for foodtech order',
            ['order' => 42]
        )->shouldBeCalledOnce();

        $this->deliveryManager->calculateRoute(Argument::any())->shouldNotBeCalled();
        $this->pricingManager->getProductVariantsWithPricingStrategy(Argument::any(), Argument::any())->shouldNotBeCalled();

        ($this->handler)($message);
    }

    public function testNonFoodtechOrderCalculatesRouteWithoutPriceRecalculation(): void
    {
        $message = $this->buildMessage(42, false);

        $delivery = $this->prophesize(Delivery::class);

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getDelivery()->willReturn($delivery->reveal());
        $order->isFoodtech()->willReturn(false);

        $this->orderRepository->find(42)->willReturn($order->reveal());

        $this->deliveryManager->calculateRoute($delivery->reveal())->shouldBeCalledOnce();
        $this->pricingManager->getProductVariantsWithPricingStrategy(Argument::any(), Argument::any())->shouldNotBeCalled();
        $this->eventBus->dispatch(Argument::any())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldNotBeCalled();

        ($this->handler)($message);
    }

    public function testArbitaryPriceIsPreservedOnRecalculation(): void
    {
        $message = $this->buildMessage(42, true);

        $delivery = $this->prophesize(Delivery::class);
        $arbitraryPrice = new ArbitraryPrice('custom', 1000);

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getDelivery()->willReturn($delivery->reveal());
        $order->isFoodtech()->willReturn(false);
        $order->getDeliveryPrice()->willReturn($arbitraryPrice);

        $this->orderRepository->find(42)->willReturn($order->reveal());

        $this->deliveryManager->calculateRoute($delivery->reveal())->shouldBeCalledOnce();

        $this->feeCalculationLogger->info(
            'Keeping arbitrary price after task cancellation',
            ['order' => 42]
        )->shouldBeCalledOnce();

        $this->pricingManager->getProductVariantsWithPricingStrategy(Argument::any(), Argument::any())->shouldNotBeCalled();
        $this->eventBus->dispatch(Argument::any())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldNotBeCalled();

        ($this->handler)($message);
    }

    public function testOrderWithoutStoreSkipsPriceRecalculation(): void
    {
        $message = $this->buildMessage(42, true);

        $nonArbitraryPrice = $this->prophesize(\AppBundle\Entity\Sylius\PriceInterface::class);

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getStore()->willReturn(null);

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getDelivery()->willReturn($delivery->reveal());
        $order->isFoodtech()->willReturn(false);
        $order->getDeliveryPrice()->willReturn($nonArbitraryPrice->reveal());

        $this->orderRepository->find(42)->willReturn($order->reveal());

        $this->deliveryManager->calculateRoute($delivery->reveal())->shouldBeCalledOnce();

        $this->feeCalculationLogger->info(
            'Skipping price recalculation for order without a Store',
            ['order' => 42]
        )->shouldBeCalledOnce();

        $this->pricingManager->getProductVariantsWithPricingStrategy(Argument::any(), Argument::any())->shouldNotBeCalled();
        $this->eventBus->dispatch(Argument::any())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldNotBeCalled();

        ($this->handler)($message);
    }

    public function testPriceIsRecalculatedAndEventDispatched(): void
    {
        $message = $this->buildMessage(42, true);

        $store = $this->prophesize(Store::class);
        $nonArbitraryPrice = $this->prophesize(\AppBundle\Entity\Sylius\PriceInterface::class);
        $manualSupplements = $this->prophesize(ManualSupplements::class);
        $productVariants = [/* mock variants */];

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getStore()->willReturn($store->reveal());

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getDelivery()->willReturn($delivery->reveal());
        $order->isFoodtech()->willReturn(false);
        $order->getDeliveryPrice()->willReturn($nonArbitraryPrice->reveal());
        $order->getManualSupplements()->willReturn($manualSupplements->reveal());
        $order->getTotal()->willReturn(2000);
        $order->getTaxTotal()->willReturn(200);

        $this->orderRepository->find(42)->willReturn($order->reveal());

        $this->deliveryManager->calculateRoute($delivery->reveal())->shouldBeCalledOnce();

        $this->pricingManager
            ->getProductVariantsWithPricingStrategy(
                $delivery->reveal(),
                Argument::type(CalculateUsingPricingRules::class)
            )
            ->willReturn($productVariants)
            ->shouldBeCalledOnce();

        $this->pricingManager
            ->processDeliveryOrder($order->reveal(), $productVariants)
            ->shouldBeCalledOnce();

        $this->eventBus
            ->dispatch(Argument::type(OrderPriceUpdated::class))
            ->willReturn(new Envelope(new \stdClass()))
            ->shouldBeCalledOnce();

        $this->entityManager->flush()->shouldBeCalledOnce();

        $this->feeCalculationLogger->info(
            'Recalculated price after task cancellation',
            Argument::type('array')
        )->shouldBeCalledOnce();

        ($this->handler)($message);
    }

    public function testDeliveryPriceExceptionLogsWarningAndReturnsEarly(): void
    {
        $message = $this->buildMessage(42, true);

        $delivery = $this->prophesize(Delivery::class);

        $order = $this->prophesize(OrderInterface::class);
        $order->getId()->willReturn(42);
        $order->getDelivery()->willReturn($delivery->reveal());
        $order->isFoodtech()->willReturn(false);
        $order->getDeliveryPrice()->willThrow(new \Exception('price error'));

        $this->orderRepository->find(42)->willReturn($order->reveal());

        $this->deliveryManager->calculateRoute($delivery->reveal())->shouldBeCalledOnce();

        $this->feeCalculationLogger->warning(
            'Failed to get delivery price',
            ['order' => 42]
        )->shouldBeCalledOnce();

        $this->pricingManager->getProductVariantsWithPricingStrategy(Argument::any(), Argument::any())->shouldNotBeCalled();
        $this->eventBus->dispatch(Argument::any())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldNotBeCalled();

        ($this->handler)($message);
    }
}
