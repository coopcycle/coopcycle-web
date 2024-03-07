<?php

namespace AppBundle\Entity\Incident;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;


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

    protected string $status;

    protected ArrayCollection $tasks;

    protected ?string $failure_reason_code;

    protected ?string $description;

    protected ArrayCollection $images;

    protected $created_at;

    protected $updated_at;

    const STATUS_NEW = 'NEW';
    const STATUS_OPEN = 'OPEN';
    const STATUS_CLOSED = 'CLOSED';
    const STATUS_RESOLVED = 'RESOLVED';


    public function __construct() {
        $this->tasks = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): int {
        return $this->id;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function setStatus(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function getTasks(): ArrayCollection {
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

    public function getImages(): ArrayCollection {
        return $this->images;
    }

    public function addImage(IncidentImage $image): self {
        $this->images[] = $image;
        return $this;
    }

    public function getCreatedAt(): mixed {
        return $this->created_at;
    }

    public function getUpdatedAt(): mixed {
        return $this->updated_at;
    }

}
