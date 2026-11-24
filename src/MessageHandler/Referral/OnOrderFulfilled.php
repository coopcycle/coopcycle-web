<?php

namespace AppBundle\MessageHandler\Referral;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Service\EmailManager;
use AppBundle\Service\Referral\ReferralLevelResolver;
use AppBundle\Service\Referral\ReferralProgramStatus;
use AppBundle\Service\Referral\ReferralRewardCouponFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Confirms a pending referral once the referred customer's first order has
 * actually been fulfilled.
 *
 * Deliberately *not* CheckoutSucceeded, which is where this used to live:
 * that event fires at the end of the checkout request, before the restaurant
 * has accepted anything, and -- for cash on delivery and for free orders --
 * without any payment having been processed at all (CheckoutHandler skips
 * offline payments outright, and card payments are merely *authorized*
 * there; CapturePayment only captures them on this very event). Rewarding at
 * checkout meant that an order the restaurant then refused, or one the
 * customer cancelled, still minted a referrer coupon, incremented the public
 * successful-referral counter and could fire a level-up email -- none of
 * which can be taken back once the coupon has been redeemed, which is why
 * this is prevented at the trigger rather than revoked after the fact.
 *
 * OrderFulfilled is the first point at which the order definitely happened:
 * card payments are captured on it, and a cash-on-delivery order has by then
 * been handed over and paid for in cash. Orders that never get that far are
 * swept up by ExpireReferralsCommand once the pending TTL passes.
 */
#[AsMessageHandler()]
class OnOrderFulfilled
{
    public function __construct(
        private readonly ReferralProgramStatus $referralProgramStatus,
        private readonly ReferralRepository $referralRepository,
        private readonly OrderRepository $orderRepository,
        private readonly ReferralLevelResolver $referralLevelResolver,
        private readonly ReferralRewardCouponFactory $referralRewardCouponFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailManager $emailManager)
    {
    }

    public function __invoke(OrderFulfilled $event): void
    {
        if (!$this->referralProgramStatus->isActive()) {
            return;
        }

        $order = $event->getOrder();
        $customer = $order->getCustomer();

        if (!$customer instanceof Customer) {
            return;
        }

        $referral = $this->referralRepository->findPendingByReferredCustomer($customer);

        if (null === $referral) {
            return;
        }

        // Orders *placed before* this one, rather than a plain count of the
        // customer's paid orders: now that the reward is granted at
        // fulfillment, a customer who ordered again while their first order
        // was still out for delivery would otherwise look like a repeat
        // customer at the moment that first order lands, and lose their
        // referrer the reward.
        $earlierPaidOrderCount = $this->orderRepository->countPaidOrdersPlacedBefore($customer, $order);

        if ($earlierPaidOrderCount > 0) {
            // Stale pending referral: this customer already had a paid order
            // before this one, so it isn't genuinely their first -- don't
            // reward retroactively.
            $referral->markAsExpired();
            $this->entityManager->flush();

            return;
        }

        $referrer = $referral->getReferrer();
        $previousLevel = $this->referralLevelResolver->resolve($referrer->getSuccessfulReferralCount());

        $referral->markAsCompleted($order);
        $referrer->incrementSuccessfulReferralCount();

        $level = $this->referralLevelResolver->resolve($referrer->getSuccessfulReferralCount());

        if (null !== $level) {
            $referral->setReferrerRewardCoupon(
                $this->referralRewardCouponFactory->createReferrerRewardCoupon($referrer, $level)
            );
        }

        $this->entityManager->flush();

        if (null !== $level) {
            $this->emailManager->sendTo(
                $this->emailManager->createReferralCompletedMessageForReferrer($referral),
                $referrer->getEmail()
            );

            if ($level !== $previousLevel) {
                $this->emailManager->sendTo(
                    $this->emailManager->createReferralLevelUpMessage($referrer, $level),
                    $referrer->getEmail()
                );
            }
        }
    }
}
