<?php

namespace AppBundle\Service\Referral;

use RZ\CanonicalEmail\Exception\EmailNotSupported;
use RZ\CanonicalEmail\Strategy\CanonizeStrategy;

/**
 * Strips plus-addressing (foo+alias@example.com -> foo@example.com) and
 * lowercases the local part, for every domain.
 *
 * rezozero/canonical-email only does this for providers it recognizes:
 * Gmail, outlook.com, or -- via a blocking DNS MX lookup -- Google Workspace
 * domains. Plus-addressing is far more widely supported than that, and here
 * we're guarding against someone deliberately gaming the referral program,
 * so an unknown domain shouldn't be a free pass.
 *
 * Erring on the aggressive side is the safe direction: two genuinely
 * different mailboxes wrongly treated as one person only ever costs a
 * referral reward, whereas a missed alias hands out a reward for a fake
 * referral.
 */
class PlusAddressStrategy implements CanonizeStrategy
{
    public function supportsEmailAddress(string $emailAddress): bool
    {
        return false !== filter_var($emailAddress, FILTER_VALIDATE_EMAIL);
    }

    public function getCanonicalEmailAddress(string $emailAddress): string
    {
        if (!$this->supportsEmailAddress($emailAddress)) {
            throw EmailNotSupported::fromEmailAddressAndStrategy($emailAddress, static::class);
        }

        // Split on the last "@", as a quoted local part may legally contain one.
        $atPosition = strrpos($emailAddress, '@');
        $localPart = strtolower(substr($emailAddress, 0, $atPosition));
        $domain = substr($emailAddress, $atPosition + 1);

        $plusPosition = strpos($localPart, '+');
        // A leading "+" would leave an empty local part, which would collapse
        // every "+something@domain" address into the same canonical form.
        if (false !== $plusPosition && $plusPosition > 0) {
            $localPart = substr($localPart, 0, $plusPosition);
        }

        return sprintf('%s@%s', $localPart, $domain);
    }
}
