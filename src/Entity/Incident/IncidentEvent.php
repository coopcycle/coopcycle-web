<?php

namespace AppBundle\Entity\Incident;

use AppBundle\Entity\User;
use Symfony\Component\Serializer\Annotation\Groups;

class IncidentEvent
{
    /**
     * @Groups({"incident"})
     */
    protected $id;


    /**
     * @Groups({"incident"})
     */
    protected $type;


    /**
     * @Groups({"incident"})
     */
    protected $message;


    /**
     * @Groups({"incident"})
     */
    protected ?array $metadata;

    protected $incident;


    /**
     * @Groups({"incident"})
     */
    protected $createdBy;

    /**
     * @Groups({"incident"})
     */
    protected $createdAt;

    const TYPE_COMMENT = "commented";
    const TYPE_RESCHEDULE = "rescheduled";
    const TYPE_APPLY_PRICE_DIFF = "applied_price_diff";
    const TYPE_CANCEL_TASK = "cancelled";
    const TYPE_TRANSPORTER_REPORT = "transporter_reported";

    public function getId()
    {
        return $this->id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setMetadata($metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getIncident()
    {
        return $this->incident;
    }

    public function setIncident(Incident $incident): self
    {
        $this->incident = $incident;
        return $this;
    }

    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function __toString()
    {
        return $this->getType();
    }
}
