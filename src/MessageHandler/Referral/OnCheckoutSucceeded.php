<?php

namespace AppBundle\MessageHandler\Referral;

use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Service\Referral\ReferralLevelResolver;
use AppBundle\Service\Referral\ReferralRewardCouponFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Confirms a pending referral once the referred customer's first paid order
 * goes through -- CheckoutSucceeded, not an order-acceptance/fulfillment
 * event, because it's the earliest point at which "this is a genuine, paid
 * order" is true, independent of restaurant acceptance workflow.
 */
#[AsMessageHandler()]
class OnCheckoutSucceeded
{
    public function __construct(
        private readonly bool $referralProgramEnabled,
        private readonly ReferralRepository $referralRepository,
        private readonly OrderRepository $orderRepository,
        private readonly ReferralLevelResolver $referralLevelResolver,
        private readonly ReferralRewardCouponFactory $referralRewardCouponFactory,
        private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(CheckoutSucceeded $event): void
    {
        if (!$this->referralProgramEnabled) {
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

        $paidOrderCount = $this->orderRepository->countPaidOrdersByCustomer($customer);

        if ($paidOrderCount > 1) {
            // Stale pending referral: this customer already had a paid order
            // before this one, so it isn't genuinely their first -- don't
            // reward retroactively.
            $referral->markAsExpired();
            $this->entityManager->flush();

            return;
        }

        $referrer = $referral->getReferrer();

        $referral->markAsCompleted($order);
        $referrer->incrementSuccessfulReferralCount();

        $level = $this->referralLevelResolver->resolve($referrer->getSuccessfulReferralCount());

        if (null !== $level) {
            $referral->setReferrerRewardCoupon(
                $this->referralRewardCouponFactory->createReferrerRewardCoupon($referrer, $level)
            );
        }

        $this->entityManager->flush();
    }
}
