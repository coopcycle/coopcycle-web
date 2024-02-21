<?php

namespace Tests\AppBundle\Sylius\Cart;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
use AppBundle\Service\NullLoggingUtils;
use AppBundle\Sylius\Cart\RestaurantCartContext;
use AppBundle\Sylius\Cart\RestaurantResolver;
use AppBundle\Sylius\Cart\SessionStorage;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;

class RestaurantCartContextTest extends TestCase
{
    use ProphecyTrait;

    private $orderRepository;
    private $orderFactory;

    private $tokenStorage;

    private $context;

    public function setUp(): void
    {
        $this->orderFactory = $this->prophesize(OrderFactory::class);
        $this->storage = $this->prophesize(SessionStorage::class);
        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->restaurantResolver = $this->prophesize(RestaurantResolver::class);
        $this->authorizationChecker = $this->prophesize(AuthorizationCheckerInterface::class);
        $this->security = $this->prophesize(Security::class);
        $this->businessContext = $this->prophesize(BusinessContext::class);

        $this->webChannel = $this->prophesize(ChannelInterface::class);
        $this->webChannel->getCode()->willReturn('web');

        $this->channelContext->getChannel()->willReturn($this->webChannel->reveal());

        $this->businessContext->isActive()->willReturn(false);

        $this->context = new RestaurantCartContext(
            $this->orderFactory->reveal(),
            $this->storage->reveal(),
            $this->channelContext->reveal(),
            $this->restaurantResolver->reveal(),
            $this->authorizationChecker->reveal(),
            $this->security->reveal(),
            $this->businessContext->reveal(),
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

        $this->storage
            ->has()
            ->willReturn(false);

        $cart = $this->context->getCart();
    }

    public function testNothingStoredInSession()
    {
        $this->storage
            ->has()
            ->willReturn(false);

        $restaurant = $this->prophesize(LocalBusiness::class)->reveal();

        $this->restaurantResolver
            ->resolve()
            ->willReturn($restaurant);

        $expectedCart = $this->prophesize(OrderInterface::class);
        $expectedCart->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $expectedCart->getCreatedAt()->willReturn(new \DateTime());
        $expectedCart->getShippingAddress()->willReturn(null);

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

        $expectedCart = $this->prophesize(Order::class);
        $expectedCart->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $expectedCart->getRestaurant()->willReturn($restaurant->reveal());
        $expectedCart->getVendor()->willReturn(
            $restaurant->reveal()
        );
        $expectedCart
            ->isMultiVendor()
            ->willReturn(false);
        $expectedCart->getChannel()->willReturn($this->webChannel->reveal());
        $expectedCart->getShippingAddress()->willReturn(null);

        $this->storage
            ->has()
            ->willReturn(true);

        $this->storage
            ->get()
            ->willReturn($expectedCart->reveal());

        $this->restaurantResolver
            ->accept($expectedCart->reveal())
            ->willReturn(true);

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

        $expectedCart = $this->prophesize(Order::class);
        $expectedCart->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $expectedCart->getRestaurant()->willReturn($restaurant->reveal());
        $expectedCart->getChannel()->willReturn($this->webChannel->reveal());
        $expectedCart->getVendor()->willReturn(
            $restaurant->reveal()
        );
        $expectedCart
            ->isMultiVendor()
            ->willReturn(false);
        $expectedCart->getShippingAddress()->willReturn(null);

        $this->storage
            ->has()
            ->willReturn(true);

        $this->storage
            ->get()
            ->willReturn($expectedCart->reveal());

        $this->restaurantResolver
            ->accept($expectedCart->reveal())
            ->willReturn(false);

        $cart = $this->context->getCart();

        $this->assertNotNull($cart);
        $this->assertSame($expectedCart->reveal(), $cart);

        $expectedCart->clearItems()->shouldHaveBeenCalled();
        $expectedCart->setShippingTimeRange(null)->shouldHaveBeenCalled();
    }

    public function testNonExistingCartStoredInSession()
    {
        $restaurantProphecy = $this->prophesize(LocalBusiness::class);
        $restaurant = $restaurantProphecy->reveal();

        $this->restaurantResolver
            ->resolve()
            ->willReturn($restaurant);

        $this->storage
            ->has()
            ->willReturn(true);

        $this->storage
            ->get()
            ->willReturn(null);

        // $this->orderRepository
        //     ->findCartById(1)
        //     ->willReturn(null);

        $this->storage
            ->remove()
            ->shouldBeCalled();

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $cartProphecy->getCreatedAt()->willReturn(new \DateTime());
        $cartProphecy->getShippingAddress()->willReturn(null);

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

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getVendor()->willReturn($restaurant->reveal());
        $cartProphecy->isMultiVendor()->willReturn(false);
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());

        $this->storage
            ->has()
            ->willReturn(true);

        $this->storage
            ->get()
            ->willReturn($cartProphecy->reveal());

        $this->storage->remove()->shouldBeCalled();

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

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getCustomer()->willReturn($this->prophesize(CustomerInterface::class));
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getVendor()->willReturn($restaurant->reveal());
        $cartProphecy->isMultiVendor()->willReturn(false);
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());
        $cartProphecy->getShippingAddress()->willReturn(null);

        $expectedCart = $cartProphecy->reveal();

        $this->storage
            ->has()
            ->willReturn(true);

        $this->storage
            ->get()
            ->willReturn($expectedCart);

        $this->storage->remove()->shouldNotBeCalled();

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

        $cartProphecy = $this->prophesize(OrderInterface::class);
        $cartProphecy->getRestaurant()->willReturn($restaurant->reveal());
        $cartProphecy->getVendor()->willReturn($restaurant->reveal());
        $cartProphecy->isMultiVendor()->willReturn(false);
        $cartProphecy->getChannel()->willReturn($this->webChannel->reveal());

        $expectedCart = $cartProphecy->reveal();

        $this->storage
            ->has()
            ->willReturn(true);

        $this->storage
            ->get()
            ->willReturn($expectedCart);

        $this->storage->remove()->shouldBeCalled();

        $cart = $this->context->getCart();
    }

    public function testNonExistingCartStoredInSessionWithNoRestaurantInContext()
    {
        $this->expectException(CartNotFoundException::class);

        $this->restaurantResolver
            ->resolve()
            ->willReturn(null);

        $this->storage
            ->has()
            ->willReturn(true);

        $this->storage
            ->get()
            ->willReturn(null);

        $this->storage
            ->remove()
            ->shouldBeCalled();

        $cart = $this->context->getCart();
    }
}
