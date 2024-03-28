<?php

namespace AppBundle\Entity\Incident;

use AppBundle\Entity\User;

class IncidentEvent
{
    protected $id;

    protected $type;

    protected $message;

    protected $metadata;

    protected $incident;

    protected $createdBy;

    protected $createdAt;

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
