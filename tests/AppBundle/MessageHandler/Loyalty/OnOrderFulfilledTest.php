<?php

namespace Tests\AppBundle\MessageHandler\Loyalty;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\MessageHandler\Loyalty\OnOrderFulfilled;
use AppBundle\Service\Loyalty\LoyaltyPointsManager;
use AppBundle\Service\Loyalty\LoyaltyProgramStatus;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class OnOrderFulfilledTest extends TestCase
{
    use ProphecyTrait;

    private $loyaltyPointsManager;
    private $entityManager;

    protected function setUp(): void
    {
        $this->loyaltyPointsManager = $this->prophesize(LoyaltyPointsManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
    }

    private function createHandler(bool $active = true): OnOrderFulfilled
    {
        $loyaltyProgramStatus = $this->prophesize(LoyaltyProgramStatus::class);
        $loyaltyProgramStatus->isActive()->willReturn($active);

        return new OnOrderFulfilled(
            $loyaltyProgramStatus->reveal(),
            $this->loyaltyPointsManager->reveal(),
            $this->entityManager->reveal()
        );
    }

    public function testDoesNothingWhenFeatureDisabled(): void
    {
        $order = $this->prophesize(OrderInterface::class);

        $this->loyaltyPointsManager->creditForOrder(Argument::cetera())->shouldNotBeCalled();

        $handler = $this->createHandler(false);
        $handler(new OrderFulfilled($order->reveal()));

        $this->entityManager->flush()->shouldNotHaveBeenCalled();
    }

    public function testDoesNotPersistWhenOrderEarnsNothing(): void
    {
        $order = $this->prophesize(OrderInterface::class);
        $orderReveal = $order->reveal();

        $this->loyaltyPointsManager->creditForOrder($orderReveal)->willReturn(null);

        $handler = $this->createHandler();
        $handler(new OrderFulfilled($orderReveal));

        $this->entityManager->persist(Argument::any())->shouldNotHaveBeenCalled();
        $this->entityManager->flush()->shouldNotHaveBeenCalled();
    }

    /**
     * Points are credited on fulfillment, not at checkout -- an order the
     * restaurant refuses, or one the customer cancels, never reaches this
     * handler and so never earns anything to claw back.
     */
    public function testCreditsPointsOnFulfilledOrder(): void
    {
        $order = $this->prophesize(OrderInterface::class);
        $orderReveal = $order->reveal();

        $entry = LoyaltyPointsEntry::credit(new Customer(), 23);

        $this->loyaltyPointsManager->creditForOrder($orderReveal)->willReturn($entry);
        $this->entityManager->persist($entry)->shouldBeCalledOnce();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $handler = $this->createHandler();
        $handler(new OrderFulfilled($orderReveal));
    }
}
