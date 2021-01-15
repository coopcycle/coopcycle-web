<?php

namespace Tests\AppBundle\Sylius\Promotion\Checker\Rule;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

class IsRestaurantRuleCheckerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->objectRepository = $this->prophesize(ObjectRepository::class);

        $this->doctrine = $this->prophesize(ManagerRegistry::class);
        $this->doctrine->getRepository(LocalBusiness::class)->willReturn($this->objectRepository->reveal());

        $this->ruleChecker = new IsRestaurantRuleChecker(
            $this->doctrine->reveal()
        );
    }

    public function testIsEligibleWithoutRestaurant()
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getRestaurant()->willReturn(null);

        $configuration = [
            'restaurant_id' => 1
        ];

        $this->assertFalse($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }

    public function testIsEligibleNonExistingRestaurant()
    {
        $restaurant = new Restaurant();

        $order = $this->prophesize(OrderInterface::class);
        $order->getRestaurant()->willReturn($restaurant);

        $this->objectRepository->find(1)->willReturn(null);

        $configuration = [
            'restaurant_id' => 1
        ];

        $this->assertFalse($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }

    public function testIsEligibleWithAnotherRestaurant()
    {
        $restaurant = new Restaurant();
        $other = new Restaurant();

        $order = $this->prophesize(OrderInterface::class);
        $order->getRestaurant()->willReturn($restaurant);

        $this->objectRepository->find(1)->willReturn($other);

        $configuration = [
            'restaurant_id' => 1
        ];

        $this->assertFalse($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }

    public function testIsEligibleWithSameRestaurant()
    {
        $restaurant = new Restaurant();

        $order = $this->prophesize(OrderInterface::class);
        $order->getRestaurant()->willReturn($restaurant);

        $this->objectRepository->find(1)->willReturn($restaurant);

        $configuration = [
            'restaurant_id' => 1
        ];

        $this->assertTrue($this->ruleChecker->isEligible($order->reveal(), $configuration));
    }
}
