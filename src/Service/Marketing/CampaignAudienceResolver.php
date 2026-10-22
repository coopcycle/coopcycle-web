<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\CampaignRecipientRepository;
use AppBundle\Entity\Marketing\EmailSuppressionRepository;
use AppBundle\Entity\OptinConsentRepository;
use AppBundle\Enum\Optin;
use AppBundle\Service\RfmSegmentCalculator;
use AppBundle\Service\SettingsManager;

/**
 * Works out who a campaign goes to, from the RFM data as it stands right
 * now.
 *
 * Computed at send time rather than when the campaign is written, so a
 * customer who reordered this morning isn't emailed tonight as "at risk"
 * because that's what they were last week.
 */
class CampaignAudienceResolver
{
    public const DEFAULT_FREQUENCY_CAP_DAYS = 7;

    public function __construct(
        private readonly RfmSegmentCalculator $rfmSegmentCalculator,
        private readonly EmailSuppressionRepository $suppressionRepository,
        private readonly OptinConsentRepository $optinConsentRepository,
        private readonly CampaignRecipientRepository $recipientRepository,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function resolve(string $segment, ?\DateTime $now = null): CampaignAudience
    {
        $now = $now ?? new \DateTime();

        $rows = array_values(array_filter(
            $this->rfmSegmentCalculator->computeRows(),
            fn (array $row) => ($row['segment'] ?? null) === $segment
        ));

        $segmentSize = count($rows);

        // An RFM row is built from orders, which a guest can place without
        // ever having an account to consent with.
        $withEmail = array_values(array_filter($rows, fn (array $row) => !empty($row['email'])));
        $excludedNoEmail = $segmentSize - count($withEmail);

        if (empty($withEmail)) {
            return new CampaignAudience([], $segmentSize, $excludedNoEmail);
        }

        $emails = array_map(fn (array $row) => (string) $row['email'], $withEmail);
        $customerIds = array_map(fn (array $row) => (int) $row['id'], $withEmail);

        // Three bulk lookups rather than three per customer: a segment can
        // run to thousands of rows, and this runs while an admin waits.
        $suppressed = $this->suppressionRepository->findSuppressed($emails);
        $consented = $this->optinConsentRepository->findCustomerIdsWithConsent(Optin::MARKETING, $customerIds);
        $recentlyEmailed = $this->recipientRepository->findRecentlyEmailed(
            $emails,
            (clone $now)->modify(sprintf('-%d days', $this->getFrequencyCapDays()))
        );

        $recipients = [];
        $excludedSuppressed = 0;
        $excludedNoConsent = 0;
        $excludedRecentlyEmailed = 0;

        foreach ($withEmail as $row) {
            $email = mb_strtolower(trim((string) $row['email']));

            // Order matters only for the counts, and this is the order an
            // admin would reason in: unreachable, then not allowed, then
            // allowed but not yet.
            if (isset($suppressed[$email])) {
                $excludedSuppressed++;
                continue;
            }

            if (!isset($consented[(int) $row['id']])) {
                $excludedNoConsent++;
                continue;
            }

            if (isset($recentlyEmailed[$email])) {
                $excludedRecentlyEmailed++;
                continue;
            }

            $recipients[] = new CampaignRecipientCandidate(
                (int) $row['id'],
                $email,
                $row['first_name'] ?? null,
                $row['last_name'] ?? null
            );
        }

        return new CampaignAudience(
            $recipients,
            $segmentSize,
            $excludedNoEmail,
            $excludedSuppressed,
            $excludedNoConsent,
            $excludedRecentlyEmailed
        );
    }

    public function getFrequencyCapDays(): int
    {
        $configured = $this->settingsManager->get('marketing_frequency_cap_days');

        return null === $configured || '' === $configured
            ? self::DEFAULT_FREQUENCY_CAP_DAYS
            : (int) $configured;
    }
}
