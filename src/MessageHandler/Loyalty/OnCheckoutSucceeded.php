<?php

namespace AppBundle\MessageHandler\Loyalty;

use AppBundle\Domain\Order\Event\CheckoutSucceeded;
use AppBundle\Service\Loyalty\LoyaltyPointsManager;
use AppBundle\Service\Loyalty\LoyaltyProgramStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Credits loyalty points once an order is paid for. Uses CheckoutSucceeded
 * for the same reason the referral program does: it's the earliest point at
 * which "this is a genuine, paid order" holds, independent of the
 * restaurant-acceptance workflow.
 */
#[AsMessageHandler()]
class OnCheckoutSucceeded
{
    public function __construct(
        private readonly LoyaltyProgramStatus $loyaltyProgramStatus,
        private readonly LoyaltyPointsManager $loyaltyPointsManager,
        private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(CheckoutSucceeded $event): void
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
