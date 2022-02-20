<?php

namespace AppBundle\Entity\Task;

use ApiPlatform\Core\Annotation\ApiResource;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   shortName="TaskImportQueue",
 *   normalizationContext={"groups"={"task_import_queue"}},
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "normalizationContext"={"groups"={"task_import_queue"}}
 *     }
 *   }
 * )
 */
class ImportQueue
{
    use Timestampable;

    /**
     * @Groups({"task"})
     */
    protected $id;

    /**
     * @Assert\Type(type="string")
     * @Groups({"task_import_queue"})
     */
    protected $status;

    /**
     * @Groups({"task_import_queue_completed"})
     */
    protected $group;

    protected $startedAt;

    protected $finishedAt;

    /**
     * @Groups({"task_import_queue_failed"})
     */
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
     * @param Group $group
     *
     * @return self
     */
    public function setGroup(Group $group)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @return Group
     */
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

    /**
     * @Groups({"task_import_queue_completed"})
     * @SerializedName("tasks")
     */
    public function getTasks(): array
    {
        return $this->group->getTasks();
    }
}
