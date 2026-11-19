<?php

namespace Tests\AppBundle\MessageHandler\Referral;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Entity\Referral\Referral;
use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\MessageHandler\Referral\OnOrderFulfilled;
use AppBundle\Service\EmailManager;
use AppBundle\Service\Referral\ReferralLevelResolver;
use AppBundle\Service\Referral\ReferralProgramStatus;
use AppBundle\Service\Referral\ReferralRewardCouponFactory;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class OnOrderFulfilledTest extends TestCase
{
    use ProphecyTrait;

    private $referralRepository;
    private $orderRepository;
    private $referralLevelResolver;
    private $referralRewardCouponFactory;
    private $entityManager;
    private $emailManager;

    protected function setUp(): void
    {
        $this->referralRepository = $this->prophesize(ReferralRepository::class);
        $this->orderRepository = $this->prophesize(OrderRepository::class);
        $this->referralLevelResolver = $this->prophesize(ReferralLevelResolver::class);
        $this->referralRewardCouponFactory = $this->prophesize(ReferralRewardCouponFactory::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->emailManager = $this->prophesize(EmailManager::class);
    }

    private function createHandler(bool $active = true): OnOrderFulfilled
    {
        $referralProgramStatus = $this->prophesize(ReferralProgramStatus::class);
        $referralProgramStatus->isActive()->willReturn($active);

        return new OnOrderFulfilled(
            $referralProgramStatus->reveal(),
            $this->referralRepository->reveal(),
            $this->orderRepository->reveal(),
            $this->referralLevelResolver->reveal(),
            $this->referralRewardCouponFactory->reveal(),
            $this->entityManager->reveal(),
            $this->emailManager->reveal()
        );
    }

    public function testDoesNothingWhenFeatureDisabled(): void
    {
        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->shouldNotBeCalled();

        $handler = $this->createHandler(false);
        $handler(new OrderFulfilled($order->reveal()));

        $this->entityManager->flush()->shouldNotHaveBeenCalled();
    }

    public function testDoesNothingWhenNoPendingReferral(): void
    {
        $customer = new Customer();

        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->willReturn($customer);

        $this->referralRepository->findPendingByReferredCustomer($customer)->willReturn(null);

        $handler = $this->createHandler();
        $handler(new OrderFulfilled($order->reveal()));

        $this->entityManager->flush()->shouldNotHaveBeenCalled();
    }

    public function testExpiresStaleReferralWhenNotActuallyFirstOrder(): void
    {
        $referrer = new Customer();
        $customer = new Customer();

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setReferred($customer);

        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->willReturn($customer);

        $this->referralRepository->findPendingByReferredCustomer($customer)->willReturn($referral);
        $this->orderRepository->countPaidOrdersPlacedBefore($customer, Argument::any())->willReturn(1);

        $this->referralRewardCouponFactory->createReferrerRewardCoupon(Argument::cetera())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $handler = $this->createHandler();
        $handler(new OrderFulfilled($order->reveal()));

        $this->assertSame(Referral::STATUS_EXPIRED, $referral->getStatus());
        $this->assertSame(0, $referrer->getSuccessfulReferralCount());
    }

    public function testCompletesReferralAndMintsRewardCouponOnFirstOrder(): void
    {
        $referrer = new Customer();
        $referrer->setEmail('referrer@example.com');
        $customer = new Customer();

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setReferred($customer);

        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->willReturn($customer);
        $orderReveal = $order->reveal();

        $level = new ReferralLevel();
        $coupon = $this->prophesize(PromotionCouponInterface::class)->reveal();

        $this->referralRepository->findPendingByReferredCustomer($customer)->willReturn($referral);
        $this->orderRepository->countPaidOrdersPlacedBefore($customer, Argument::any())->willReturn(0);
        $this->referralLevelResolver->resolve(0)->willReturn(null);
        $this->referralLevelResolver->resolve(1)->willReturn($level);
        $this->referralRewardCouponFactory->createReferrerRewardCoupon($referrer, $level)->willReturn($coupon);
        $this->entityManager->flush()->shouldBeCalledOnce();

        $rewardMessage = (new \Symfony\Component\Mime\Email())->subject('reward');
        $this->emailManager->createReferralCompletedMessageForReferrer($referral)->willReturn($rewardMessage);
        $this->emailManager->sendTo($rewardMessage, 'referrer@example.com')->shouldBeCalledOnce();

        $levelUpMessage = (new \Symfony\Component\Mime\Email())->subject('level up');
        $this->emailManager->createReferralLevelUpMessage($referrer, $level)->willReturn($levelUpMessage);
        $this->emailManager->sendTo($levelUpMessage, 'referrer@example.com')->shouldBeCalledOnce();

        $handler = $this->createHandler();
        $handler(new OrderFulfilled($orderReveal));

        $this->assertSame(Referral::STATUS_COMPLETED, $referral->getStatus());
        $this->assertSame($orderReveal, $referral->getTriggeringOrder());
        $this->assertSame($coupon, $referral->getReferrerRewardCoupon());
        $this->assertSame(1, $referrer->getSuccessfulReferralCount());
    }

    /**
     * The reward is granted at fulfillment, so by the time this handler runs
     * the customer may already have placed further orders -- which must not
     * make their genuine first order look like a repeat one. The handler has
     * to ask how many orders came *before* the triggering one, never for a
     * plain total.
     */
    public function testStillRewardsWhenCustomerOrderedAgainBeforeFirstOrderWasFulfilled(): void
    {
        $referrer = new Customer();
        $customer = new Customer();

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setReferred($customer);

        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->willReturn($customer);
        $orderReveal = $order->reveal();

        $this->referralRepository->findPendingByReferredCustomer($customer)->willReturn($referral);
        // Nothing before this order, even though the customer now has two.
        $this->orderRepository->countPaidOrdersPlacedBefore($customer, $orderReveal)->willReturn(0);
        $this->orderRepository->countPaidOrdersByCustomer(Argument::cetera())->shouldNotBeCalled();
        $this->referralLevelResolver->resolve(Argument::any())->willReturn(null);
        $this->entityManager->flush()->shouldBeCalledOnce();

        $handler = $this->createHandler();
        $handler(new OrderFulfilled($orderReveal));

        $this->assertSame(Referral::STATUS_COMPLETED, $referral->getStatus());
        $this->assertSame(1, $referrer->getSuccessfulReferralCount());
    }

    public function testCompletesReferralWithoutCouponWhenNoLevelReached(): void
    {
        $referrer = new Customer();
        $customer = new Customer();

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setReferred($customer);

        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->willReturn($customer);

        $this->referralRepository->findPendingByReferredCustomer($customer)->willReturn($referral);
        $this->orderRepository->countPaidOrdersPlacedBefore($customer, Argument::any())->willReturn(0);
        $this->referralLevelResolver->resolve(0)->willReturn(null);
        $this->referralLevelResolver->resolve(1)->willReturn(null);

        $this->referralRewardCouponFactory->createReferrerRewardCoupon(Argument::cetera())->shouldNotBeCalled();
        $this->emailManager->sendTo(Argument::cetera())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $handler = $this->createHandler();
        $handler(new OrderFulfilled($order->reveal()));

        $this->assertSame(Referral::STATUS_COMPLETED, $referral->getStatus());
        $this->assertNull($referral->getReferrerRewardCoupon());
    }
}
