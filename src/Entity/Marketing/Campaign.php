<?php

namespace AppBundle\Entity\Marketing;

use AppBundle\Sylius\Promotion\PromotionCouponInterface;

/**
 * One marketing send to one RFM segment.
 *
 * The audience is deliberately NOT stored here. It's resolved fresh when the
 * campaign actually goes out, so a customer who reordered this morning isn't
 * emailed tonight as "at risk" because that's what they were when the
 * campaign was written.
 */
class Campaign
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';

    protected ?int $id = null;

    protected string $name;

    protected string $segment;

    protected ?string $subject = null;

    /**
     * What the editor round-trips. The HTML below is what's actually sent --
     * keeping both means a campaign can be reopened and edited without
     * reverse-engineering its markup.
     */
    protected ?string $bodyMjml = null;

    protected ?string $bodyHtml = null;

    protected string $status = self::STATUS_DRAFT;

    protected ?\DateTime $scheduledAt = null;

    protected ?\DateTime $sentAt = null;

    /**
     * Snapshotted when the campaign goes out, rather than read from settings
     * at display time, so history still says who it came from after an admin
     * changes the sender.
     */
    protected ?string $senderName = null;

    protected ?string $senderEmail = null;

    protected ?PromotionCouponInterface $promotionCoupon = null;

    protected ?\DateTime $createdAt = null;

    protected ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function setSegment(string $segment): void
    {
        $this->segment = $segment;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getBodyMjml(): ?string
    {
        return $this->bodyMjml;
    }

    public function setBodyMjml(?string $bodyMjml): void
    {
        $this->bodyMjml = $bodyMjml;
    }

    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(?string $bodyHtml): void
    {
        $this->bodyHtml = $bodyHtml;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isDraft(): bool
    {
        return self::STATUS_DRAFT === $this->status;
    }

    /**
     * Content is frozen once a campaign is on its way out: editing a subject
     * halfway through a send would mean two different emails going out under
     * one campaign, with no record of which recipient got which.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    public function getScheduledAt(): ?\DateTime
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTime $scheduledAt): void
    {
        $this->scheduledAt = $scheduledAt;
    }

    public function getSentAt(): ?\DateTime
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTime $sentAt): void
    {
        $this->sentAt = $sentAt;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): void
    {
        $this->senderName = $senderName;
    }

    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(?string $senderEmail): void
    {
        $this->senderEmail = $senderEmail;
    }

    public function getPromotionCoupon(): ?PromotionCouponInterface
    {
        return $this->promotionCoupon;
    }

    public function setPromotionCoupon(?PromotionCouponInterface $promotionCoupon): void
    {
        $this->promotionCoupon = $promotionCoupon;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }
}
