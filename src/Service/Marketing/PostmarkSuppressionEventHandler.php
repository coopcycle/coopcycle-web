<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\EmailSuppression;
use Psr\Log\LoggerInterface;

/**
 * Translates a Postmark webhook payload into a suppression change.
 *
 * Deliberately separate from the controller: this is the part worth testing,
 * and it has nothing to do with HTTP.
 *
 * @see https://postmarkapp.com/developer/webhooks/webhooks-overview
 */
class PostmarkSuppressionEventHandler
{
    private const DEFAULT_STREAM = 'outbound';

    public function __construct(
        private readonly EmailSuppressionManager $suppressionManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool whether the payload changed anything
     */
    public function handle(array $payload): bool
    {
        return match ($payload['RecordType'] ?? null) {
            'Bounce' => $this->handleBounce($payload),
            'SpamComplaint' => $this->handleSpamComplaint($payload),
            'SubscriptionChange' => $this->handleSubscriptionChange($payload),
            // Postmark posts every event type a stream has enabled (opens,
            // clicks, deliveries). Anything that isn't about suppression is
            // not an error, it's just not ours.
            default => false,
        };
    }

    private function handleBounce(array $payload): bool
    {
        $email = $payload['Email'] ?? null;

        if (empty($email)) {
            return $this->ignore('Bounce', 'no Email');
        }

        // A soft bounce is a transient failure -- a full mailbox, a server
        // having a bad day. Postmark only deactivates the address once it
        // gives up on it, and that is the only point at which it should stop
        // being emailed.
        if (true !== ($payload['Inactive'] ?? false)) {
            return false;
        }

        $this->suppressionManager->suppress(
            $email,
            $payload['MessageStream'] ?? self::DEFAULT_STREAM,
            EmailSuppression::REASON_HARD_BOUNCE,
            $this->parseDate($payload['BouncedAt'] ?? null)
        );

        return true;
    }

    private function handleSpamComplaint(array $payload): bool
    {
        $email = $payload['Email'] ?? null;

        if (empty($email)) {
            return $this->ignore('SpamComplaint', 'no Email');
        }

        $this->suppressionManager->suppress(
            $email,
            $payload['MessageStream'] ?? self::DEFAULT_STREAM,
            EmailSuppression::REASON_SPAM_COMPLAINT,
            $this->parseDate($payload['BouncedAt'] ?? null)
        );

        return true;
    }

    private function handleSubscriptionChange(array $payload): bool
    {
        // Note the field name: this event carries "Recipient", not "Email".
        $email = $payload['Recipient'] ?? null;

        if (empty($email)) {
            return $this->ignore('SubscriptionChange', 'no Recipient');
        }

        $stream = $payload['MessageStream'] ?? self::DEFAULT_STREAM;

        // The same event reports resubscribing, with the flag flipped.
        if (true !== ($payload['SuppressSending'] ?? false)) {
            $this->suppressionManager->unsuppress($email, $stream);

            return true;
        }

        $this->suppressionManager->suppress(
            $email,
            $stream,
            self::reasonFor(
                $payload['SuppressionReason'] ?? null,
                $payload['Origin'] ?? null
            ),
            $this->parseDate($payload['ChangedAt'] ?? null)
        );

        return true;
    }

    /**
     * Postmark reports an unsubscribe and an admin-side suppression under the
     * same "ManualSuppression" reason, and only tells them apart by Origin --
     * which is the difference between "they asked us to stop" and "we stopped
     * ourselves", and so worth keeping.
     */
    private static function reasonFor(?string $suppressionReason, ?string $origin): string
    {
        return match ($suppressionReason) {
            'HardBounce' => EmailSuppression::REASON_HARD_BOUNCE,
            'SpamComplaint' => EmailSuppression::REASON_SPAM_COMPLAINT,
            'ManualSuppression' => 'Recipient' === $origin
                ? EmailSuppression::REASON_UNSUBSCRIBE
                : EmailSuppression::REASON_MANUAL,
            default => EmailSuppression::REASON_MANUAL,
        };
    }

    private function parseDate(?string $date): ?\DateTime
    {
        if (empty($date)) {
            return null;
        }

        try {
            return new \DateTime($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function ignore(string $recordType, string $why): bool
    {
        $this->logger->warning(sprintf('Ignoring Postmark %s payload: %s', $recordType, $why));

        return false;
    }
}
