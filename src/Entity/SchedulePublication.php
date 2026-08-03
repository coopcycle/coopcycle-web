<?php

namespace AppBundle\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Marks the schedule of a week (identified by its Monday) as published.
 * While a week is draft (no row), couriers can't see or apply to its shifts;
 * once published, they are notified by email and applications open.
 *
 * Publishing goes through POST /api/shifts/publish_week (side effects:
 * notification emails) — this resource is read-only.
 */
#[ApiResource(
    shortName: 'SchedulePublication',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
    ],
    normalizationContext: ['groups' => ['schedule_publication']]
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['weekStart' => 'exact'])]
class SchedulePublication
{
    #[Groups(['schedule_publication'])]
    protected $id;

    #[Groups(['schedule_publication'])]
    protected ?\DateTime $weekStart = null;

    protected ?UserInterface $publishedBy = null;

    #[Groups(['schedule_publication'])]
    protected $createdAt;

    protected $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekStart(): ?\DateTime
    {
        return $this->weekStart;
    }

    public function setWeekStart(\DateTime $weekStart): self
    {
        $this->weekStart = $weekStart;

        return $this;
    }

    public function getPublishedBy(): ?UserInterface
    {
        return $this->publishedBy;
    }

    public function setPublishedBy(?UserInterface $publishedBy): self
    {
        $this->publishedBy = $publishedBy;

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
}
