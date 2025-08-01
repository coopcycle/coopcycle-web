<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(operations: [new Get(security: 'is_granted(\'view\', object.getTask())')])]
class TaskEvent
{
    private $id;

    private $task;

    #[Groups(['task'])]
    private $name;

    #[Groups(['task'])]
    private array $data = [];

    private array $metadata = [];

    #[Groups(['task'])]
    private $createdAt;

    public function __construct(
        Task $task,
        $name,
        array $data = [],
        array $metadata = [],
        ?\DateTime $createdAt = null)
    {
        if (null === $createdAt) {
            $createdAt = new \DateTime();
        }

        $this->task = $task;
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->data = $data;
        $this->metadata = $metadata;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getData($name = null)
    {
        if (null === $name) {
            return $this->data;
        }

        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
    }

    /**
     * FIXME setData should be allowed only on new events
     */
    public function setData($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
