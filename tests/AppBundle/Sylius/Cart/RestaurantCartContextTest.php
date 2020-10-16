<?php

namespace Tests\AppBundle\Sylius\Cart;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Sylius\Cart\RestaurantCartContext;
use Doctrine\ORM\EntityNotFoundException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RestaurantCartContextTest extends TestCase
{
    use ProphecyTrait;

    private $session;
    private $orderRepository;
    private $orderFactory;
    private $restaurantRepository;
    private $sessionKeyName = 'foo';

    private $context;

    public function setUp(): void
    {
        $this->session = $this->prophesize(SessionInterface::class);
        $this->orderRepository = $this->prophesize(OrderRepositoryInterface::class);
        $this->orderFactory = $this->prophesize(OrderFactory::class);
        $this->restaurantRepository = $this->prophesize(LocalBusinessRepository::class);
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);

        $this->webChannel = $this->prophesize(ChannelInterface::class);
        $this->webChannel->getCode()->willReturn('web');

        $this->channelContext->getChannel()->willReturn($this->webChannel->reveal());

        $this->context = new RestaurantCartContext(
            $this->session->reveal(),
            $this->orderRepository->reveal(),
            $this->orderFactory->reveal(),
            $this->restaurantRepository->reveal(),
            $this->sessionKeyName,
            $this->channelContext->reveal()
        );
    }

    public function testNoRestaurantSessionKey()
    {
        $this->expectException(CartNotFoundException::class);

        $this->session
            ->has('restaurantId')
            ->willReturn(false);

        $cart = $this->context->getCart();
    }

    public function testNothingStoredInSession()
    {
        $this->session
            ->has('restaurantId')
            ->willReturn(true);

        $this->session
            ->get('restaurantId')
            ->willReturn(1);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(false);

        $restaurant = $this->prophesize(LocalBusiness::class)->reveal();

        $this->restaurantRepository
            ->find(1)
            ->willReturn($restaurant);

        $expectedCart = $this->prophesize(OrderInterface::class)->reveal();

        $this->orderFactory
            ->createForRestaurant($restaurant)
            ->shouldBeCalled()
            ->willReturn($expectedCart);

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart, $cart);
    }

    public function testExistingCartStoredInSession()
    {
        $this->session
            ->has('restaurantId')
            ->willReturn(true);

        $this->session
            ->get('restaurantId')
            ->willReturn(1);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getName()->willReturn('Foo');

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());

        $expectedCart = $cartProphecy->reveal();

        $this->orderRepository
            ->findCartById(1)
            ->willReturn($expectedCart);

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart, $cart);
    }

    public function testNonExistingCartStoredInSession()
    {
        $this->session
            ->has('restaurantId')
            ->willReturn(true);

        $this->session
            ->get('restaurantId')
            ->willReturn(1);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $this->orderRepository
            ->findCartById(1)
            ->willReturn(null);

        $restaurantProphecy = $this->prophesize(LocalBusiness::class);
        $restaurant = $restaurantProphecy->reveal();

        $this->restaurantRepository
            ->find(1)
            ->willReturn($restaurant);

        $this->session
            ->remove($this->sessionKeyName)
            ->shouldBeCalled();

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $expectedCart = $cartProphecy->reveal();

        $this->orderFactory
            ->createForRestaurant($restaurant)
            ->shouldBeCalled()
            ->willReturn($expectedCart);

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart, $cart);
    }

    public function testExistingCartStoredInSessionWithDisabledRestaurant()
    {
        $this->expectException(CartNotFoundException::class);

        $this->session
            ->has('restaurantId')
            ->willReturn(true);

        $this->session
            ->get('restaurantId')
            ->willReturn(1);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getName()->willThrow(new EntityNotFoundException());

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());

        $expectedCart = $cartProphecy->reveal();

        $this->orderRepository
            ->findCartById(1)
            ->willReturn($expectedCart);

        $this->session->remove($this->sessionKeyName)->shouldBeCalled();
        $this->session->remove('restaurantId')->shouldBeCalled();

        $cart = $this->context->getCart();
    }
}
