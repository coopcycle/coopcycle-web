<?php

namespace AppBundle\Entity\Task;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Task\Bulk as TaskBulk;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Validator\Constraints\TaskGroup as AssertTaskGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
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
 *       "security"="is_granted('ROLE_OAUTH2_TASKS') and object.isAllowed(oauth2_context.store)"
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
     * @Groups({"task_group"})
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

    public function isAllowed(Store $store)
    {
        foreach ($this->getTasks() as $task) {

            $organization = $task->getOrganization();

            if ($organization === null) {
                return false;
            }

            if ($organization !== $store->getOrganization()) {
                return false;
            }
        }

        return true;
    }
}
