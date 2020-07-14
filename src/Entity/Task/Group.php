<?php

namespace AppBundle\Entity\Task;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Task\Bulk as TaskBulk;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   shortName="TaskGroup",
 *   attributes={
 *     "normalization_context"={"groups"={"task"}}
 *   },
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
 *       "method"="GET"
 *     }
 *   }
 * )
 */
class Group implements TaggableInterface
{
    use TaggableTrait;

    /**
     * @Groups({"task"})
     */
    protected $id;

    /**
     * @Groups({"task"})
     * @Assert\Type(type="string")
     */
    protected $name;

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

    public function getTasks()
    {
        return $this->tasks;
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
