<?php

namespace AppBundle\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\Filter\HolidayRequestDateFilter;
use AppBundle\Api\State\HolidayRequestProcessor;
use AppBundle\Api\State\HolidayRequestStatusProcessor;
use AppBundle\Api\State\MyHolidayRequestsProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: false,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Post(
            security: 'is_granted(\'ROLE_COURIER\')',
            processor: HolidayRequestProcessor::class
        ),
        new Get(security: 'is_granted(\'ROLE_DISPATCHER\') or object.getUser() == user'),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\') or (object.getUser() == user and object.isPending())'),
        new Put(
            uriTemplate: '/holiday_requests/{id}/approve',
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            processor: HolidayRequestStatusProcessor::class,
            denormalizationContext: ['groups' => ['holiday_request_action']]
        ),
        new Put(
            uriTemplate: '/holiday_requests/{id}/reject',
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            processor: HolidayRequestStatusProcessor::class,
            denormalizationContext: ['groups' => ['holiday_request_action']]
        ),
        new GetCollection(
            uriTemplate: '/me/holiday_requests',
            paginationEnabled: false,
            provider: MyHolidayRequestsProvider::class,
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
    ],
    normalizationContext: ['groups' => ['holiday_request']],
    denormalizationContext: ['groups' => ['holiday_request_create']]
)]
#[ApiFilter(filterClass: HolidayRequestDateFilter::class, properties: ['date'])]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['status' => 'exact'])]
class HolidayRequest
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    #[Groups(['holiday_request'])]
    protected $id;

    #[Groups(['holiday_request'])]
    protected ?UserInterface $user = null;

    #[Groups(['holiday_request', 'holiday_request_create'])]
    #[Assert\NotBlank]
    protected ?\DateTime $startDate = null;

    #[Groups(['holiday_request', 'holiday_request_create'])]
    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate')]
    protected ?\DateTime $endDate = null;

    #[Groups(['holiday_request'])]
    protected string $status = self::STATUS_PENDING;

    #[Groups(['holiday_request', 'holiday_request_create'])]
    #[Assert\Length(max: 65535)]
    protected ?string $comment = null;

    #[Groups(['holiday_request'])]
    protected ?UserInterface $actionedBy = null;

    #[Groups(['holiday_request'])]
    protected ?\DateTime $actionedAt = null;

    #[Groups(['holiday_request'])]
    protected $createdAt;

    protected $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTime $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getActionedBy(): ?UserInterface
    {
        return $this->actionedBy;
    }

    public function setActionedBy(?UserInterface $actionedBy): self
    {
        $this->actionedBy = $actionedBy;

        return $this;
    }

    public function getActionedAt(): ?\DateTime
    {
        return $this->actionedAt;
    }

    public function setActionedAt(?\DateTime $actionedAt): self
    {
        $this->actionedAt = $actionedAt;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function containsDate(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');

        return $day >= $this->startDate->format('Y-m-d') && $day <= $this->endDate->format('Y-m-d');
    }
}
