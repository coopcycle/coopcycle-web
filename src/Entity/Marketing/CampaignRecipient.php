<?php

namespace AppBundle\Entity\Marketing;

use AppBundle\Entity\Sylius\Customer;

/**
 * One person a campaign was sent to, and what happened.
 *
 * Doubles as the record the frequency cap reads: "has this address had a
 * campaign recently" is answered from here, which is why a row is written
 * for every attempt rather than only for successes.
 */
class CampaignRecipient
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUPPRESSED = 'suppressed';

    protected ?int $id = null;

    protected Campaign $campaign;

    /**
     * Nullable so deleting a customer doesn't erase the fact that a campaign
     * went out to them -- the address stays either way.
     */
    protected ?Customer $customer = null;

    protected string $email;

    protected string $status = self::STATUS_PENDING;

    protected ?string $messageId = null;

    protected ?string $error = null;

    protected ?\DateTime $sentAt = null;

    protected ?\DateTime $createdAt = null;

    public static function create(Campaign $campaign, string $email, ?Customer $customer = null): self
    {
        $recipient = new self();
        $recipient->campaign = $campaign;
        $recipient->customer = $customer;
        $recipient->email = mb_strtolower(trim($email));

        return $recipient;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): Campaign
    {
        return $this->campaign;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markAsSent(?string $messageId = null): void
    {
        $this->status = self::STATUS_SENT;
        $this->messageId = $messageId;
        $this->sentAt = new \DateTime();
    }

    public function markAsFailed(string $error): void
    {
        $this->status = self::STATUS_FAILED;
        // Truncated: a provider error can be long, and the useful part is at
        // the front.
        $this->error = mb_substr($error, 0, 255);
    }

    public function markAsSuppressed(): void
    {
        $this->status = self::STATUS_SUPPRESSED;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getSentAt(): ?\DateTime
    {
        return $this->sentAt;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }
}
