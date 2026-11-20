<?php

namespace AppBundle\Service\Referral;

use RZ\CanonicalEmail\EmailCanonizer;
use RZ\CanonicalEmail\Strategy\GmailStrategy;
use RZ\CanonicalEmail\Strategy\LowercaseDomainStrategy;

/**
 * Builds the canonizer the referral program compares addresses with.
 *
 * The order matters and is the reason this lives in PHP rather than being
 * spelled out in services.yaml: strategies are applied in sequence, and
 * GmailStrategy detects its own domains with a case-sensitive regex, so
 * anything@Gmail.com would slip past it -- and keep its (meaningless to
 * Gmail) dots -- unless the domain has already been lowercased.
 *
 * GSuiteStrategy is left out on purpose: it resolves MX records over DNS on
 * every call, which has no business blocking a signup.
 */
final class ReferralEmailCanonizerFactory
{
    public static function create(): EmailCanonizer
    {
        return new EmailCanonizer([
            new LowercaseDomainStrategy(),
            new PlusAddressStrategy(),
            new GmailStrategy(),
        ]);
    }
}
