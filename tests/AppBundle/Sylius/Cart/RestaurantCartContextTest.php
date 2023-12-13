<?php

namespace Tests\AppBundle\Sylius\Cart;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
use AppBundle\Service\NullLoggingUtils;
use AppBundle\Sylius\Cart\RestaurantCartContext;
use AppBundle\Sylius\Cart\RestaurantResolver;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityNotFoundException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RestaurantCartContextTest extends TestCase
{
    use ProphecyTrait;

    private $session;
    private $orderRepository;
    private $orderFactory;

    private $tokenStorage;
    private $sessionKeyName = 'foo';

    private $context;

    public function setUp(): void
    {
        $this->session = $this->prophesize(SessionInterface::class);
        $this->orderRepository = $this->prophesize(OrderRepositoryInterface::class);
        $this->orderFactory = $this->prophesize(OrderFactory::class);
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->restaurantResolver = $this->prophesize(RestaurantResolver::class);
        $this->authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $this->tokenStorage = $this->prophesize(TokenStorageInterface::class);

        $this->webChannel = $this->prophesize(ChannelInterface::class);
        $this->webChannel->getCode()->willReturn('web');

        $this->channelContext->getChannel()->willReturn($this->webChannel->reveal());

        $this->context = new RestaurantCartContext(
            $this->session->reveal(),
            $this->orderRepository->reveal(),
            $this->orderFactory->reveal(),
            $this->sessionKeyName,
            $this->channelContext->reveal(),
            $this->restaurantResolver->reveal(),
            $this->authorizationChecker->reveal(),
            $this->tokenStorage->reveal(),
            new NullLogger(),
            new NullLoggingUtils()
        );
    }

    public function testNoRestaurantSessionKey()
    {
        $this->expectException(CartNotFoundException::class);

        $this->restaurantResolver
            ->resolve()
            ->willReturn(null);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(false);

        $cart = $this->context->getCart();
    }

    public function testNothingStoredInSession()
    {
        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(false);

        $restaurant = $this->prophesize(LocalBusiness::class)->reveal();

        $this->restaurantResolver
            ->resolve()
            ->willReturn($restaurant);

        $expectedCart = $this->prophesize(OrderInterface::class);
        $expectedCart->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $expectedCart->getCreatedAt()->willReturn(new \DateTime());

        $this->orderFactory
            ->createForRestaurant($restaurant)
            ->shouldBeCalled()
            ->willReturn($expectedCart->reveal());

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart->reveal(), $cart);

        // Multiple calls should not recreate an instance
        $this->assertSame($cart, $this->context->getCart());
    }

    public function testExistingCartStoredInSessionWithSameRestaurant()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getName()->willReturn('Foo');
        $restaurant->isEnabled()->willReturn(true);

        $this->restaurantResolver
            ->resolve()
            ->willReturn($restaurant->reveal());

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $expectedCart = $this->prophesize(Order::class);
        $expectedCart->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $expectedCart->getRestaurant()->willReturn($restaurant->reveal());
        $expectedCart->getVendor()->willReturn(
            Vendor::withRestaurant(
                $restaurant->reveal()
            )
        );
        $expectedCart
            ->isMultiVendor()
            ->willReturn(false);
        $expectedCart->getChannel()->willReturn($this->webChannel->reveal());

        $this->restaurantResolver
            ->accept($expectedCart->reveal())
            ->willReturn(true);

        $this->orderRepository
            ->findCartById(1)
            ->willReturn($expectedCart->reveal());

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart->reveal(), $cart);

        $expectedCart->clearItems()->shouldNotHaveBeenCalled();
    }

    public function testExistingCartStoredInSessionWithAnotherRestaurant()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->getName()->willReturn('Foo');
        $restaurant->isEnabled()->willReturn(true);

        $otherRestaurant = $this->prophesize(LocalBusiness::class);

        $this->restaurantResolver
            ->resolve()
            ->willReturn($otherRestaurant->reveal());

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $expectedCart = $this->prophesize(Order::class);
        $expectedCart->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $expectedCart->getRestaurant()->willReturn($restaurant->reveal());
        $expectedCart->getChannel()->willReturn($this->webChannel->reveal());
        $expectedCart->getVendor()->willReturn(
            Vendor::withRestaurant(
                $restaurant->reveal()
            )
        );
        $expectedCart
            ->isMultiVendor()
            ->willReturn(false);

        $this->restaurantResolver
            ->accept($expectedCart->reveal())
            ->willReturn(false);

        $this->orderRepository
            ->findCartById(1)
            ->willReturn($expectedCart->reveal());

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart->reveal(), $cart);

        $expectedCart->clearItems()->shouldHaveBeenCalled();
        $expectedCart->setShippingTimeRange(null)->shouldHaveBeenCalled();
        $expectedCart->setRestaurant($otherRestaurant->reveal())->shouldHaveBeenCalled();
    }

    public function testNonExistingCartStoredInSession()
    {
        $restaurantProphecy = $this->prophesize(LocalBusiness::class);
        $restaurant = $restaurantProphecy->reveal();

        $this->restaurantResolver
            ->resolve()
            ->willReturn($restaurant);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $this->orderRepository
            ->findCartById(1)
            ->willReturn(null);

        $this->session
            ->remove($this->sessionKeyName)
            ->shouldBeCalled();

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $cartProphecy->getCreatedAt()->willReturn(new \DateTime());
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

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->isEnabled()->willReturn(false);

        $this->restaurantResolver
            ->resolve()
            ->willReturn(null);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getVendor()->willReturn(Vendor::withRestaurant($restaurant->reveal()));
        $cartProphecy->isMultiVendor()->willReturn(false);
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());

        $expectedCart = $cartProphecy->reveal();

        $this->orderRepository
            ->findCartById(1)
            ->willReturn($expectedCart);

        $this->session->remove($this->sessionKeyName)->shouldBeCalled();

        $cart = $this->context->getCart();
    }

    public function testExistingCartStoredInSessionWithDisabledRestaurantAndAuthorizedUser()
    {
        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->isEnabled()->willReturn(false);

        $this->authorizationChecker->isGranted('edit', $restaurant->reveal())->willReturn(true);

        $this->restaurantResolver
            ->resolve()
            ->willReturn(null);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getVendor()->willReturn(Vendor::withRestaurant($restaurant->reveal()));
        $cartProphecy->isMultiVendor()->willReturn(false);
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());

        $expectedCart = $cartProphecy->reveal();

        $this->orderRepository
            ->findCartById(1)
            ->willReturn($expectedCart);

        $this->session->remove($this->sessionKeyName)->shouldNotBeCalled();

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart, $cart);
    }

    public function testExistingCartStoredInSessionThrowingEntityNotFoundException()
    {
        $this->expectException(CartNotFoundException::class);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $restaurant->isEnabled()->willThrow(new EntityNotFoundException());

        $this->restaurantResolver
            ->resolve()
            ->willReturn(null);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getVendor()->willReturn(Vendor::withRestaurant($restaurant->reveal()));
        $cartProphecy->isMultiVendor()->willReturn(false);
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());

        $expectedCart = $cartProphecy->reveal();

        $this->orderRepository
            ->findCartById(1)
            ->willReturn($expectedCart);

        $this->session->remove($this->sessionKeyName)->shouldBeCalled();

        $cart = $this->context->getCart();
    }

    public function testNonExistingCartStoredInSessionWithNoRestaurantInContext()
    {
        $this->expectException(CartNotFoundException::class);

        $this->restaurantResolver
            ->resolve()
            ->willReturn(null);

        $this->session
            ->has($this->sessionKeyName)
            ->willReturn(true);

        $this->session
            ->get($this->sessionKeyName)
            ->willReturn(1);

        $this->orderRepository
            ->findCartById(1)
            ->willReturn(null);

        $this->session
            ->remove($this->sessionKeyName)
            ->shouldBeCalled();

        $cart = $this->context->getCart();
    }
}
