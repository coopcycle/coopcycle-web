<?php

namespace AppBundle\Service\Marketing;

/**
 * Who a campaign would go to, and who it wouldn't.
 *
 * The exclusion counts aren't decoration: an admin looking at "420 in this
 * segment, 38 will be emailed" needs to know whether the other 382 are
 * unreachable, unconsented, or just emailed last Tuesday, because those
 * three call for completely different responses.
 */
final class CampaignAudience
{
    /**
     * @param CampaignRecipientCandidate[] $recipients
     */
    public function __construct(
        public readonly array $recipients,
        public readonly int $segmentSize,
        public readonly int $excludedNoEmail = 0,
        public readonly int $excludedSuppressed = 0,
        public readonly int $excludedNoConsent = 0,
        public readonly int $excludedRecentlyEmailed = 0,
    ) {
    }

    public function count(): int
    {
        return count($this->recipients);
    }

    public function isEmpty(): bool
    {
        return [] === $this->recipients;
    }

    public function excludedTotal(): int
    {
        return $this->excludedNoEmail
            + $this->excludedSuppressed
            + $this->excludedNoConsent
            + $this->excludedRecentlyEmailed;
    }

    /**
     * @return string[]
     */
    public function getEmails(): array
    {
        return array_map(fn (CampaignRecipientCandidate $c) => $c->email, $this->recipients);
    }
}
