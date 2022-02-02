<?php

namespace Tests\AppBundle\Edenred;

use AppBundle\Edenred\Authentication as EdenredAuth;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Edenred\RefreshTokenHandler;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ClientTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $orderNumberAssigner;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->refreshTokenHandler = new RefreshTokenHandler(
            'http://example.com',
            'id_123',
            'secret_456',
            $this->entityManager->reveal()
        );

        $this->edenredAuth = $this->prophesize(EdenredAuth::class);

        $this->mockHandler = new MockHandler();

        $handlerStack = HandlerStack::create($this->mockHandler);

        $this->client = new EdenredClient(
            'key_123',
            'secret_456',
            $this->refreshTokenHandler,
            $this->edenredAuth->reveal(),
            ['handler' => $handlerStack]
        );
    }

    public function testGetBalance()
    {
        $order = $this->prophesize(Order::class);

        $customer = new Customer();
        $customer->setEdenredAccessToken('access_123');
        $customer->setEdenredRefreshToken('refresh_123');

        $this->edenredAuth->userInfo($customer)->willReturn(['username' => 'John']);

        $order->getTotal()->willReturn(3000);
        $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT)->willReturn(350);
        $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->willReturn(0);
        $order->getCustomer()->willReturn($customer);

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'data' => [
                    'available_amount' => 3800,
                ]
            ]))
        );

        $this->assertEquals(3800, $this->client->getBalance($customer));
    }

    public function testSplitAmounts()
    {
        $order = $this->prophesize(Order::class);

        $customer = new Customer();
        $customer->setEdenredAccessToken('access_123');
        $customer->setEdenredRefreshToken('refresh_123');

        $this->edenredAuth->userInfo($customer)->willReturn(['username' => 'John']);

        $order->getTotal()->willReturn(3000);

        $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT)->willReturn(350);
        $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->willReturn(0);
        $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT)->willReturn(0);
        $order->getAlcoholicItemsTotal()->willReturn(0);

        $order->getCustomer()->willReturn($customer);

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'data' => [
                    'available_amount' => 3800,
                ]
            ]))
        );

        $amounts = $this->client->splitAmounts($order->reveal());

        $this->assertEquals(2650, $amounts['edenred']);
        $this->assertEquals(350, $amounts['card']);
    }

    public function testSplitAmountsFullEdenred()
    {
        $order = $this->prophesize(Order::class);

        $customer = new Customer();
        $customer->setEdenredAccessToken('access_123');
        $customer->setEdenredRefreshToken('refresh_123');

        $this->edenredAuth->userInfo($customer)->willReturn(['username' => 'John']);

        $order->getTotal()->willReturn(3000);

        $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT)->willReturn(0);
        $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->willReturn(0);
        $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT)->willReturn(0);
        $order->getAlcoholicItemsTotal()->willReturn(0);

        $order->getCustomer()->willReturn($customer);

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'data' => [
                    'available_amount' => 3800,
                ]
            ]))
        );

        $amounts = $this->client->splitAmounts($order->reveal());

        $this->assertEquals(3000, $amounts['edenred']);
        $this->assertEquals(0, $amounts['card']);
    }

    public function testSplitAmountsWithRemaingingEdenredAmount()
    {
        $order = $this->prophesize(Order::class);

        $customer = new Customer();
        $customer->setEdenredAccessToken('access_123');
        $customer->setEdenredRefreshToken('refresh_123');

        $this->edenredAuth->userInfo($customer)->willReturn(['username' => 'John']);

        $order->getTotal()->willReturn(3000);

        $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT)->willReturn(0);
        $order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT)->willReturn(0);
        $order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT)->willReturn(0);
        $order->getAlcoholicItemsTotal()->willReturn(0);

        $order->getCustomer()->willReturn($customer);

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'data' => [
                    'available_amount' => 1200,
                ]
            ]))
        );

        $amounts = $this->client->splitAmounts($order->reveal());

        $this->assertEquals(1200, $amounts['edenred']);
        $this->assertEquals(1800, $amounts['card']);
    }
}
