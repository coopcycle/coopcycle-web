<?php

namespace AppBundle\Entity\Task;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Task\AddToGroup as AddTasksToGroup;
use AppBundle\Action\Task\Bulk as TaskBulk;
use AppBundle\Action\Task\BulkAsync as TaskBulkAsync;
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

#[ApiResource(operations: [new Get(normalizationContext: ['groups' => ['task_group']], security: 'is_granted(\'view\', object)'), new Put(denormalizationContext: ['groups' => ['task_group']], security: 'is_granted(\'edit\', object)'), new Delete(controller: DeleteGroup::class, security: 'is_granted(\'edit\', object)'), new Post(uriTemplate: '/task_groups/{id}/tasks', controller: AddToGroup::class, deserialize: false, write: false, security: 'is_granted(\'edit\', object)'), new Post(uriTemplate: '/tasks/import', inputFormats: ['csv' => ['text/csv']], denormalizationContext: ['groups' => ['task', 'task_create']], controller: Bulk::class, security: 'is_granted(\'ROLE_OAUTH2_TASKS\') or is_granted(\'ROLE_ADMIN\')'), new Post(uriTemplate: '/tasks/import_async', inputFormats: ['csv' => ['text/csv']], deserialize: false, controller: BulkAsync::class, security: 'is_granted(\'ROLE_OAUTH2_TASKS\') or is_granted(\'ROLE_ADMIN\')'), new Post(securityPostDenormalize: 'is_granted(\'create\', object)')], shortName: 'TaskGroup', normalizationContext: ['groups' => ['task_group']])]
#[AssertTaskGroup]
class Group implements TaggableInterface
{
    use TaggableTrait;

    #[Groups(['task', 'task_group'])]
    protected $id;

    #[Groups(['task', 'task_group'])]
    #[Assert\Type(type: 'string')]
    protected $name;

    #[Assert\Valid]
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
     */
    #[Groups(['task_group'])]
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
