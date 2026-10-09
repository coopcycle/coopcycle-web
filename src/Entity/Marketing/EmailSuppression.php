<?php

namespace AppBundle\Entity\Marketing;

/**
 * An address Postmark will no longer deliver to on a given message stream,
 * mirrored into the platform.
 *
 * Postmark enforces its own suppression list regardless of what we store, so
 * this copy isn't what stops the send -- it's what lets us leave suppressed
 * customers out of an audience before sending (rather than quietly having
 * them dropped afterwards), and show why on the customer page.
 *
 * Suppressions are per stream, as they are at Postmark: unsubscribing from
 * marketing must not stop someone's order receipts.
 */
class EmailSuppression
{
    public const REASON_HARD_BOUNCE = 'hard_bounce';
    public const REASON_SPAM_COMPLAINT = 'spam_complaint';
    public const REASON_UNSUBSCRIBE = 'unsubscribe';
    public const REASON_MANUAL = 'manual';

    protected ?int $id = null;

    protected string $email;

    protected string $messageStream;

    protected string $reason;

    protected ?\DateTime $suppressedAt = null;

    protected ?\DateTime $createdAt = null;

    public static function create(
        string $email,
        string $messageStream,
        string $reason,
        ?\DateTime $suppressedAt = null): self
    {
        $suppression = new self();
        $suppression->setEmail($email);
        $suppression->messageStream = $messageStream;
        $suppression->reason = $reason;
        $suppression->suppressedAt = $suppressedAt ?? new \DateTime();

        return $suppression;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Lowercased, because that is how addresses are matched here and at
     * Postmark. Deliberately nothing more: unlike the referral program's
     * canonicalization, plus-addressing is NOT stripped -- suppressing
     * foo+news@example.com must not silence foo@example.com, which is a
     * different mailbox as far as consent goes.
     */
    public function setEmail(string $email): void
    {
        $this->email = mb_strtolower(trim($email));
    }

    public function getMessageStream(): string
    {
        return $this->messageStream;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getSuppressedAt(): ?\DateTime
    {
        return $this->suppressedAt;
    }

    public function setSuppressedAt(?\DateTime $suppressedAt): void
    {
        $this->suppressedAt = $suppressedAt;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }
}
