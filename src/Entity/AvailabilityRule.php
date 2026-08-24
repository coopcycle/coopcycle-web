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
use AppBundle\Api\Dto\AvailabilityRuleInput;
use AppBundle\Api\Dto\AvailabilityRuleUpdateInput;
use AppBundle\Api\State\AvailabilityRuleCreateProcessor;
use AppBundle\Api\State\AvailabilityRuleUpdateProcessor;
use AppBundle\Api\State\MyAvailabilityRulesProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * A recurring weekly availability declaration for an employee (e.g. "I'm
 * available Monday afternoons", "I'm unavailable Friday mornings"), useful
 * for freelancers/part-timers who aren't free all week. Unlike HolidayRequest
 * (a one-off date range needing dispatcher approval), this repeats every
 * week indefinitely and takes effect immediately — no approval workflow.
 */
#[ApiResource(
    shortName: 'AvailabilityRule',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Post(
            input: AvailabilityRuleInput::class,
            processor: AvailabilityRuleCreateProcessor::class,
            security: 'is_granted(\'ROLE_COURIER\') or is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Get(security: 'is_granted(\'ROLE_DISPATCHER\') or object.getUser() == user'),
        new Put(
            input: AvailabilityRuleUpdateInput::class,
            processor: AvailabilityRuleUpdateProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\') or object.getUser() == user'
        ),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\') or object.getUser() == user'),
        new GetCollection(
            uriTemplate: '/me/availability_rules',
            paginationEnabled: false,
            provider: MyAvailabilityRulesProvider::class,
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
    ],
    normalizationContext: ['groups' => ['availability_rule']],
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['user' => 'exact'])]
class AvailabilityRule
{
    const TYPE_AVAILABLE = 'available';
    const TYPE_UNAVAILABLE = 'unavailable';

    #[Groups(['availability_rule'])]
    protected $id;

    #[Groups(['availability_rule'])]
    protected ?UserInterface $user = null;

    #[Groups(['availability_rule'])]
    protected string $type = self::TYPE_AVAILABLE;

    /**
     * ISO day of week, 1 (Monday) to 7 (Sunday).
     */
    #[Groups(['availability_rule'])]
    protected int $dayOfWeek = 1;

    protected ?\DateTime $startTime = null;

    protected ?\DateTime $endTime = null;

    #[Groups(['availability_rule'])]
    protected ?string $comment = null;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getStartTime(): ?\DateTime
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTime $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTime
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTime $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    /**
     * Time-of-day only, as "HH:MM" — see ShiftTemplateShift for why the raw
     * \DateTime getters above must never be exposed over the API directly.
     */
    #[Groups(['availability_rule'])]
    #[SerializedName('startTime')]
    public function getStartTimeLabel(): ?string
    {
        return $this->startTime?->format('H:i');
    }

    #[Groups(['availability_rule'])]
    #[SerializedName('endTime')]
    public function getEndTimeLabel(): ?string
    {
        return $this->endTime?->format('H:i');
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

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
