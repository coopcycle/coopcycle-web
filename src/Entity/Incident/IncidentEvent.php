<?php

namespace AppBundle\Entity\Incident;

use AppBundle\Entity\User;
use Symfony\Component\Serializer\Annotation\Groups;

class IncidentEvent
{
    /**
    * @Groups({"incident_event"})
    */
    protected $id;


    /**
    * @Groups({"incident_event"})
    */
    protected $type;


    /**
    * @Groups({"incident_event"})
    */
    protected $message;


    /**
    * @Groups({"incident_event"})
    */
    protected $metadata;

    protected $incident;


    /**
    * @Groups({"incident_event"})
    */
    protected $createdBy;

    /**
    * @Groups({"incident_event"})
    */
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
