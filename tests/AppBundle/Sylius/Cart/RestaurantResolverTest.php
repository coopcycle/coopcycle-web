<?php

namespace Tests\AppBundle\Sylius\Cart;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Vendor;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Cart\RestaurantResolver;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use AppBundle\Entity\LocalBusiness;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RestaurantResolverTest extends TestCase
{
    use ProphecyTrait;

    private $requestStack;
    private $repository;
    private $entityManager;

    private $context;

    public function setUp(): void
    {
        $this->requestStack = $this->prophesize(RequestStack::class);
        $this->repository = $this->prophesize(LocalBusinessRepository::class);
    }

    public function testAcceptReturnsTrueWhenCartIsEmpty()
    {
        $cart = $this->prophesize(OrderInterface::class);

        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([]));

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsTrueWhenCartIsSavedAndContainsSameRestaurant()
    {
        $cart = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getHub()
            ->willReturn(null);

        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([ $restaurant->reveal() ]));

        $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMainRequest()->willReturn($request);

        $this->repository->find(1)->willReturn($restaurant->reveal());

        $cart->getId()->willReturn(1);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsFalseWhenCartIsSavedAndContainsAnotherRestaurant()
    {
        $cart = $this->prophesize(OrderInterface::class);

        $restaurant = $this->prophesize(LocalBusiness::class);
        $otherRestaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->getHub()
            ->willReturn(null);
        $otherRestaurant
            ->getHub()
            ->willReturn(null);

        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([ $restaurant->reveal() ]));
        $cart
            ->getBusinessAccount()
            ->willReturn(null);

            $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMainRequest()->willReturn($request);
        $this->repository->find(1)->willReturn($otherRestaurant->reveal());

        $cart->getId()->willReturn(1);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal()
        );

        $this->assertFalse($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsTrueWhenCartIsSavedWithHubAndContainsRestaurantBelongingToSameHub()
    {
        $hub = $this->prophesize(Hub::class);
        $cart = $this->prophesize(OrderInterface::class);

        $restaurant1 = $this->prophesize(LocalBusiness::class);
        $restaurant2 = $this->prophesize(LocalBusiness::class);

        $restaurant1
            ->getHub()
            ->willReturn($hub->reveal());
        $restaurant2
            ->getHub()
            ->willReturn($hub->reveal());

        $cart
            ->getRestaurants()
            ->willReturn(new ArrayCollection([ $restaurant2->reveal() ]));
        $cart
            ->getBusinessAccount()
            ->willReturn(null);

        $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMainRequest()->willReturn($request);
        $this->repository->find(1)->willReturn($restaurant1->reveal());

        $cart->getId()->willReturn(1);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }
}
