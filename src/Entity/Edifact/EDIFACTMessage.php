<?php

namespace AppBundle\Entity\Edifact;

use AppBundle\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;

class EDIFACTMessage
{
    use Timestampable;

    const TRANSPORTER_DBSCHENKER = 'DBSCHENKER';

    const DIRECTION_INBOUND = 'INBOUND';
    const DIRECTION_OUTBOUND = 'OUTBOUND';

    const MESSAGE_TYPE_SCONTR = 'SCONTR';
    const MESSAGE_TYPE_REPORT = 'REPORT';

    private int $id;

    private string $transporter;

    private string $reference;

    private string $direction;

    private string $messageType;

    private ?string $subMessageType;

    private string $edifactFile;

    private ?\DateTime $syncedAt;

    private $tasks;

    public function __construct() {
        $this->tasks = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): EDIFACTMessage
    {
        $this->id = $id;
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

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): EDIFACTMessage
    {
        $this->direction = $direction;
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
        return $this->edifactFile;
    }

    public function setEdifactFile(string $file): EDIFACTMessage
    {
        $this->edifactFile = $file;
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

    public function getTasks()
    {
        return $this->tasks;
    }

    public function addTask(Task $task): EDIFACTMessage
    {
        $this->tasks[] = $task;
        return $this;
    }

}
