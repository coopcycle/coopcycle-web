<?php

namespace AppBundle\Entity\Incident;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Api\Filter\IncidentFilter;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use AppBundle\Action\Incident\CreateComment;
use AppBundle\Action\Incident\IncidentAction;
use AppBundle\Action\Incident\IncidentFastList;
use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Validator\Constraints as AppAssert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiFilter(IncidentFilter::class, properties: ['status', 'priority', 'store', 'restaurant', 'customer', 'createdBy'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'priority'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/incidents/filters',
            /* security: 'is_granted("ROLE_DISPATCHER")', */
            controller: IncidentFastList::class,
            serialize: false
        ),
        new Get(security: 'is_granted("view", object)'),
        new Patch(security: 'is_granted("ROLE_COURIER")'),
        new Put(security: 'is_granted("ROLE_COURIER")'),
        new Post(
            uriTemplate: '/incidents/{id}/comments',
            controller: CreateComment::class,
            security: 'is_granted("ROLE_COURIER")'
        ),
        new Put(
            uriTemplate: '/incidents/{id}/action',
            controller: IncidentAction::class,
            security: 'is_granted("ROLE_COURIER")'
        ),
        new GetCollection(
            security: 'is_granted("ROLE_COURIER")',
            order: ['id' => 'DESC']
        ),
        new Post(
            controller: CreateIncident::class,
            security: 'is_granted("ROLE_COURIER")'
        ),
    ],
    normalizationContext: ['groups' => ['incident']]
)]
class Incident implements TaggableInterface {
    use TaggableTrait;

    #[Groups(['incident'])]
    protected $id;

    #[Groups(['incident'])]
    protected ?string $title = null;


    #[Groups(['incident'])]
    protected string $status = Incident::STATUS_OPEN;


    #[Groups(['incident'])]
    protected int $priority = Incident::PRIORITY_MEDIUM;


    #[Groups(['incident'])]
    protected Task $task;


    #[Groups(['incident'])]
    protected ?string $failureReasonCode = null;


    #[Groups(['incident'])]
    protected ?string $description = null;


    #[Groups(['incident'])]
    protected Collection $images;


    #[Groups(['incident'])]
    protected Collection $events;


    /**
     * FIXME: allow to set $createdBy API clients (ApiApp) and integrations
     */
    #[Groups(['incident'])]
    protected ?UserInterface $createdBy = null;

    #[Groups(['incident'])]
    #[AppAssert\IncidentMetadata]
    protected array $metadata = [];

    #[Groups(['incident'])]
    protected $createdAt;


    #[Groups(['incident'])]
    protected $updatedAt;

    const STATUS_OPEN = 'OPEN';
    const STATUS_CLOSED = 'CLOSED';

    const PRIORITY_HIGH = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_LOW = 3;


    public function __construct() {
        $this->images = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getTitle(): ?string {
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

    public function getTask(): Task {
        return $this->task;
    }

    public function setTask(Task $task): self {
        $this->task = $task;
        return $this;
    }

    public function getFailureReasonCode(): ?string {
        return $this->failureReasonCode;
    }

    public function setFailureReasonCode(?string $failureReasonCode): self {
        $this->failureReasonCode = $failureReasonCode;
        return $this;
    }

    public function getDescription(): ?string {
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

    public function getEvents(): Collection {
        return $this->events;
    }

    public function addEvent(IncidentEvent $event): self {
        $this->events[] = $event;
        return $this;
    }

    public function getCreatedBy(): ?UserInterface {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserInterface $created_by): self {
        $this->createdBy = $created_by;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function getCreatedAt(): mixed {
        return $this->createdAt;
    }

    public function getUpdatedAt(): mixed {
        return $this->updatedAt;
    }

    public function getCustomerUserInfo(): ?UserInterface {
        return $this->getTask()->getDelivery()?->getOrder()?->getCustomer()?->getUser();
    }

    /**
     * Redefined to make it serializable.
     */
    #[Groups(['incident'])]
    public function getTags(): array
    {
        return $this->tags;
    }
}
