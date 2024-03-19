<?php

namespace AppBundle\Entity\Incident;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


/**
 * @ApiResource(
 *   collectionOperations={
 *     "post"={
 *       "method"="POST",
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *     },
 *   }
 * )
 */
class Incident {

    protected int $id;

    protected string $title;

    protected string $status;

    protected int $priority;

    protected Collection $tasks;

    protected ?string $failure_reason_code = null;

    protected ?string $description = null;

    protected Collection $images;

    protected ?User $created_by = null;

    protected $createdAt;

    protected $updatedAt;

    const STATUS_OPEN = 'OPEN';
    const STATUS_CLOSED = 'CLOSED';
    const STATUS_RESOLVED = 'RESOLVED';

    const PRIORITY_HIGH = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_LOW = 3;


    public function __construct() {
        $this->tasks = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function setTitle(string $title): self {
        $this->title = $title;
        return $this;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function setStatus(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): int {
        return $this->priority;
    }

    public function setPriority(int $priority): self {
        $this->priority = $priority;
        return $this;
    }

    public function getTasks(): Collection {
        return $this->tasks;
    }

    public function addTask(Task $task): self {
        $this->tasks[] = $task;
        return $this;
    }

    public function getFailureReasonCode(): string {
        return $this->failure_reason_code;
    }

    public function setFailureReasonCode(string $failure_reason_code): self {
        $this->failure_reason_code = $failure_reason_code;
        return $this;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setDescription(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function getImages(): Collection {
        return $this->images;
    }

    public function addImage(IncidentImage $image): self {
        $this->images[] = $image;
        return $this;
    }

    public function getCreatedBy(): ?User {
        return $this->created_by;
    }

    public function setCreatedBy(?User $created_by): self {
        $this->created_by = $created_by;
        return $this;
    }

    public function getCreatedAt(): mixed {
        return $this->createdAt;
    }

    public function getUpdatedAt(): mixed {
        return $this->updatedAt;
    }

}
