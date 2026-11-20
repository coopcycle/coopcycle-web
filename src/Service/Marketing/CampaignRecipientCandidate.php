<?php

namespace AppBundle\Service\Marketing;

final class CampaignRecipientCandidate
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {
    }
}
