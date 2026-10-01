<?php

namespace AppBundle\Service\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves a referrer's current tier from their successful-referral count,
 * instead of storing a level FK anywhere -- so editing ReferralLevel
 * thresholds in the admin takes effect immediately, with no backfill.
 */
class ReferralLevelResolver
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function resolve(int $successfulReferralCount): ?ReferralLevel
    {
        $levels = $this->entityManager->getRepository(ReferralLevel::class)
            ->findBy([], ['minReferralCount' => 'DESC']);

        foreach ($levels as $level) {
            if ($successfulReferralCount >= $level->getMinReferralCount()) {
                return $level;
            }
        }

        return null;
    }

    /**
     * The next tier above the current count, for "X referrals to go"
     * progress display -- null once the referrer has reached the top level.
     */
    public function resolveNext(int $successfulReferralCount): ?ReferralLevel
    {
        $levels = $this->entityManager->getRepository(ReferralLevel::class)
            ->findBy([], ['minReferralCount' => 'ASC']);

        foreach ($levels as $level) {
            if ($level->getMinReferralCount() > $successfulReferralCount) {
                return $level;
            }
        }

        return null;
    }
}
