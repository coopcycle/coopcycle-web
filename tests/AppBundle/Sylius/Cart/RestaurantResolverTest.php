<?php

namespace Tests\AppBundle\Sylius\Cart;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Vendor;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Cart\RestaurantResolver;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
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
        $this->unitOfWork = $this->prophesize(UnitOfWork::class);
        $this->requestStack = $this->prophesize(RequestStack::class);
        $this->repository = $this->prophesize(LocalBusinessRepository::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->entityManager
            ->getUnitOfWork()
            ->willReturn($this->unitOfWork->reveal());
    }

    public function testAcceptReturnsTrueWhenThereIsNoOriginalEntityData()
    {
        $cart = $this->prophesize(OrderInterface::class);

        $this->unitOfWork
            ->getOriginalEntityData($cart->reveal())
            ->willReturn([]);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal(),
            $this->entityManager->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsTrueWhenCartIsNotSavedYet()
    {
        $cart = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(LocalBusiness::class);

        $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMasterRequest()->willReturn($request);

        $this->repository->find(1)->willReturn($restaurant->reveal());

        $cart->getId()->willReturn(null);

        $this->unitOfWork
            ->getOriginalEntityData($cart->reveal())
            ->willReturn([
                'vendor' => Vendor::withRestaurant($restaurant->reveal())
            ]);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal(),
            $this->entityManager->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsTrueWhenCartIsSavedAndContainsSameRestaurant()
    {
        $cart = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(LocalBusiness::class);

        $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMasterRequest()->willReturn($request);

        $this->repository->find(1)->willReturn($restaurant->reveal());

        $cart->getId()->willReturn(1);

        $this->unitOfWork
            ->getOriginalEntityData($cart->reveal())
            ->willReturn([
                'vendor' => Vendor::withRestaurant($restaurant->reveal())
            ]);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal(),
            $this->entityManager->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsFalseWhenCartIsSavedAndContainsAnotherRestaurant()
    {
        $cart = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(LocalBusiness::class);
        $otherRestaurant = $this->prophesize(LocalBusiness::class);

        $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMasterRequest()->willReturn($request);
        $this->repository->find(1)->willReturn($otherRestaurant->reveal());

        $cart->getId()->willReturn(1);

        $this->unitOfWork
            ->getOriginalEntityData($cart->reveal())
            ->willReturn([
                'vendor' => Vendor::withRestaurant($restaurant->reveal())
            ]);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal(),
            $this->entityManager->reveal()
        );

        $this->assertFalse($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsTrueWhenCartIsSavedAndContainsRestaurantBelongingToSameHub()
    {
        $hub = $this->prophesize(Hub::class);
        $cart = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(LocalBusiness::class);
        $otherRestaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->belongsToHub()
            ->willReturn(true);
        $restaurant
            ->getHub()
            ->willReturn($hub->reveal());

        $otherRestaurant
            ->belongsToHub()
            ->willReturn(true);
        $otherRestaurant
            ->getHub()
            ->willReturn($hub->reveal());

        $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMasterRequest()->willReturn($request);
        $this->repository->find(1)->willReturn($otherRestaurant->reveal());

        $cart->getId()->willReturn(1);

        $this->unitOfWork
            ->getOriginalEntityData($cart->reveal())
            ->willReturn([
                'vendor' => Vendor::withRestaurant($restaurant->reveal())
            ]);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal(),
            $this->entityManager->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }

    public function testAcceptReturnsTrueWhenCartIsSavedWithHubAndContainsRestaurantBelongingToSameHub()
    {
        $hub = $this->prophesize(Hub::class);
        $cart = $this->prophesize(OrderInterface::class);
        $restaurant = $this->prophesize(LocalBusiness::class);

        $restaurant
            ->belongsToHub()
            ->willReturn(true);
        $restaurant
            ->getHub()
            ->willReturn($hub->reveal());

        $request = new Request(
            $query = [],
            $request = [],
            $attributes = [
                '_route' => 'restaurant',
                'id' => 1,
            ]
        );

        $this->requestStack->getMasterRequest()->willReturn($request);
        $this->repository->find(1)->willReturn($restaurant->reveal());

        $cart->getId()->willReturn(1);

        $this->unitOfWork
            ->getOriginalEntityData($cart->reveal())
            ->willReturn([
                'vendor' => Vendor::withHub($hub->reveal())
            ]);

        $resolver = new RestaurantResolver(
            $this->requestStack->reveal(),
            $this->repository->reveal(),
            $this->entityManager->reveal()
        );

        $this->assertTrue($resolver->accept($cart->reveal()));
    }
}
