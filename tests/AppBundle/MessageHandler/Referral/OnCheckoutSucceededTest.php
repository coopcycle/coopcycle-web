<?php

namespace Tests\AppBundle\MessageHandler\Referral;

use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Entity\Referral\Referral;
use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\MessageHandler\Referral\OnCheckoutSucceeded;
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

class OnCheckoutSucceededTest extends TestCase
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

    private function createHandler(bool $active = true): OnCheckoutSucceeded
    {
        $referralProgramStatus = $this->prophesize(ReferralProgramStatus::class);
        $referralProgramStatus->isActive()->willReturn($active);

        return new OnCheckoutSucceeded(
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
        $handler(new CheckoutSucceeded($order->reveal()));

        $this->entityManager->flush()->shouldNotHaveBeenCalled();
    }

    public function testDoesNothingWhenNoPendingReferral(): void
    {
        $customer = new Customer();

        $order = $this->prophesize(OrderInterface::class);
        $order->getCustomer()->willReturn($customer);

        $this->referralRepository->findPendingByReferredCustomer($customer)->willReturn(null);

        $handler = $this->createHandler();
        $handler(new CheckoutSucceeded($order->reveal()));

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
        $this->orderRepository->countPaidOrdersByCustomer($customer)->willReturn(2);

        $this->referralRewardCouponFactory->createReferrerRewardCoupon(Argument::cetera())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $handler = $this->createHandler();
        $handler(new CheckoutSucceeded($order->reveal()));

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
        $this->orderRepository->countPaidOrdersByCustomer($customer)->willReturn(1);
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
        $handler(new CheckoutSucceeded($orderReveal));

        $this->assertSame(Referral::STATUS_COMPLETED, $referral->getStatus());
        $this->assertSame($orderReveal, $referral->getTriggeringOrder());
        $this->assertSame($coupon, $referral->getReferrerRewardCoupon());
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
        $this->orderRepository->countPaidOrdersByCustomer($customer)->willReturn(1);
        $this->referralLevelResolver->resolve(0)->willReturn(null);
        $this->referralLevelResolver->resolve(1)->willReturn(null);

        $this->referralRewardCouponFactory->createReferrerRewardCoupon(Argument::cetera())->shouldNotBeCalled();
        $this->emailManager->sendTo(Argument::cetera())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $handler = $this->createHandler();
        $handler(new CheckoutSucceeded($order->reveal()));

        $this->assertSame(Referral::STATUS_COMPLETED, $referral->getStatus());
        $this->assertNull($referral->getReferrerRewardCoupon());
    }
}
