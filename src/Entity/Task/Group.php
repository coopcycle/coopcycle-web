<?php

namespace AppBundle\Entity\Task;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Task\Bulk as TaskBulk;
use AppBundle\Action\Task\DeleteGroup as DeleteGroupController;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Validator\Constraints\TaskGroup as AssertTaskGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   shortName="TaskGroup",
 *   normalizationContext={"groups"={"task_group"}},
 *   collectionOperations={
 *     "tasks_import"={
 *       "method"="POST",
 *       "path"="/tasks/import",
 *       "input_formats"={"csv"={"text/csv"}},
 *       "denormalization_context"={"groups"={"task", "task_create"}},
 *       "controller"=TaskBulk::class,
 *       "security"="is_granted('ROLE_OAUTH2_TASKS')"
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "normalizationContext"={"groups"={"task_group"}},
 *       "security"="is_granted('view', object)"
 *     },
 *     "delete"={
 *       "method"="DELETE",
 *       "controller"=DeleteGroupController::class,
 *       "security"="is_granted('edit', object)"
 *     }
 *   }
 * )
 * @AssertTaskGroup()
 */
class Group implements TaggableInterface
{
    use TaggableTrait;

    /**
     * @Groups({"task"})
     */
    protected $id;

    /**
     * @Groups({"task", "task_group"})
     * @Assert\Type(type="string")
     */
    protected $name;

    /**
     * @Assert\Valid()
     */
    protected $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @see https://api-platform.com/docs/core/serialization/#collection-relation
     * @see https://github.com/api-platform/core/pull/1534
     *
     * @Groups({"task_group"})
     */
    public function getTasks()
    {
        return $this->tasks->getValues();
    }

    public function removeTask(Task $task)
    {
        $task->setGroup(null);

        $this->tasks->removeElement($task);
    }

    public function addTask(Task $task)
    {
        $task->setGroup($this);

        $this->tasks->add($task);
    }
}
