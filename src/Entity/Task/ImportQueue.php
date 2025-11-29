<?php

namespace AppBundle\Entity\Task;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'TaskImportQueue',
    operations: [
        new Get(normalizationContext: ['groups' => ['task_import_queue']])
    ],
    normalizationContext: ['groups' => ['task_import_queue']]
)]
class ImportQueue
{
    use Timestampable;

    #[Groups(['task'])]
    protected $id;

    #[Assert\Type(type: 'string')]
    #[Groups(['task_import_queue'])]
    protected $status;

    #[Groups(['task_import_queue_completed'])]
    protected $group;

    protected $startedAt;

    protected $finishedAt;

    #[Groups(['task_import_queue_failed'])]
    protected $error;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     *
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return self
     */
    public function setGroup(Group $group)
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setStartedAt(\DateTime $startedAt)
    {
        $this->startedAt = $startedAt;
    }

    public function setFinishedAt(\DateTime $finishedAt)
    {
        $this->finishedAt = $finishedAt;
    }

    public function setError(string $error)
    {
        $this->error = $error;
    }

    #[Groups(['task_import_queue_completed'])]
    #[SerializedName('tasks')]
    public function getTasks(): array
    {
        return $this->group->getTasks();
    }
}
