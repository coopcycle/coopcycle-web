<?php

namespace AppBundle\MessageHandler\Loyalty;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Service\Loyalty\LoyaltyPointsManager;
use AppBundle\Service\Loyalty\LoyaltyProgramStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Credits loyalty points once an order has actually been fulfilled.
 *
 * Deliberately *not* CheckoutSucceeded, which is where this used to live:
 * that event fires at the end of the checkout request, before the restaurant
 * has accepted anything, and -- for cash on delivery and for free orders --
 * without any payment having been processed at all (CheckoutHandler skips
 * offline payments outright, and card payments are merely *authorized*
 * there; CapturePayment only captures them on this very event). Crediting at
 * checkout meant an order the restaurant then refused, or one the customer
 * cancelled, still earned points -- and nothing ever took them back.
 *
 * Reversal is the wrong tool here even more than it is for referrals: by the
 * time an order is cancelled the points may already have been spent on a
 * reward coupon, so a clawback would have to either refuse (leaving the
 * balance wrong) or void a coupon the customer is holding in good faith.
 * Crediting only what was actually delivered avoids the question.
 *
 * @see AppBundle\MessageHandler\Referral\OnOrderFulfilled, which moved for
 * the same reasons.
 */
#[AsMessageHandler()]
class OnOrderFulfilled
{
    public function __construct(
        private readonly LoyaltyProgramStatus $loyaltyProgramStatus,
        private readonly LoyaltyPointsManager $loyaltyPointsManager,
        private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(OrderFulfilled $event): void
    {
        if (!$this->loyaltyProgramStatus->isActive()) {
            return;
        }

        $entry = $this->loyaltyPointsManager->creditForOrder($event->getOrder());

        if (null === $entry) {
            return;
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
