<?php

namespace AppBundle\Entity\Delivery;


use Symfony\Component\Serializer\Annotation\Groups;

class FailureReason
{
    /**
     * @Groups({"task"})
     */
    protected string $code;

    /**
     * @Groups({"task"})
     */
    protected string $description;

    /**
     * @Groups({"task"})
     */
    protected $metadata = [];

    protected $failureReasonSet;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): FailureReason
    {
        $this->code = $code;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): FailureReason
    {
        $this->description = $description;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): FailureReason
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFailureReasonSet()
    {
        return $this->failureReasonSet;
    }

    /**
     * @param mixed $failureReasonSet
     * @return FailureReason
     */
    public function setFailureReasonSet($failureReasonSet)
    {
        $this->failureReasonSet = $failureReasonSet;
        return $this;
    }

}
