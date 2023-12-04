<?php

namespace AppBundle\Entity\Edifact;

use AppBundle\Entity\Delivery;
use Gedmo\Timestampable\Traits\Timestampable;

class EDIFACTMessage
{
    use Timestampable;

    const TRANSPORTER_DBSHENKER = 'DBSHENKER';

    private int $id;

    private ?Delivery $delivery;

    private string $transporter;

    private string $reference;

    private string $messageType;

    private ?string $subMessageType;

    private string $ediMessage;

    private ?\DateTime $syncedAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): EDIFACTMessage
    {
        $this->id = $id;
        return $this;
    }

    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): EDIFACTMessage
    {
        $this->delivery = $delivery;
        return $this;
    }

    public function getTransporter(): string
    {
        return $this->transporter;
    }

    public function setTransporter(string $transporter): EDIFACTMessage
    {
        $this->transporter = $transporter;
        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): EDIFACTMessage
    {
        $this->reference = $reference;
        return $this;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function setMessageType(string $messageType): EDIFACTMessage
    {
        $this->messageType = $messageType;
        return $this;
    }

    public function getSubMessageType(): ?string
    {
        return $this->subMessageType;
    }

    public function setSubMessageType(?string $subMessageType): EDIFACTMessage
    {
        $this->subMessageType = $subMessageType;
        return $this;
    }

    public function getEdiMessage(): string
    {
        return $this->ediMessage;
    }

    public function setEdiMessage(string $ediMessage): EDIFACTMessage
    {
        $this->ediMessage = $ediMessage;
        return $this;
    }

    public function getSyncedAt(): ?\DateTime
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTime $syncedAt): EDIFACTMessage
    {
        $this->syncedAt = $syncedAt;
        return $this;
    }

}
