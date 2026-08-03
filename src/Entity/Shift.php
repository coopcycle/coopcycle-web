<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\Dto\CopyWeekInput;
use AppBundle\Api\Dto\PublishWeekInput;
use AppBundle\Api\Dto\ShiftTimeReportInput;
use AppBundle\Api\Filter\ShiftDateFilter;
use AppBundle\Api\State\CopyWeekProcessor;
use AppBundle\Api\State\MyShiftsProvider;
use AppBundle\Api\State\OpenShiftsProvider;
use AppBundle\Api\State\PublishWeekProcessor;
use AppBundle\Api\State\ShiftApplicationProcessor;
use AppBundle\Api\State\ShiftProcessor;
use AppBundle\Api\State\ShiftTimeReportProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            processor: ShiftProcessor::class
        ),
        new Post(
            uriTemplate: '/shifts/copy_week',
            input: CopyWeekInput::class,
            output: false,
            processor: CopyWeekProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            status: 204
        ),
        // Declared before /shifts/{id} so the router doesn't match "open" as an id
        new GetCollection(
            uriTemplate: '/shifts/open',
            paginationEnabled: false,
            provider: OpenShiftsProvider::class,
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
        new Get(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Put(
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            processor: ShiftProcessor::class
        ),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new GetCollection(
            uriTemplate: '/me/shifts',
            paginationEnabled: false,
            provider: MyShiftsProvider::class,
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
        new Post(
            uriTemplate: '/shifts/publish_week',
            input: PublishWeekInput::class,
            output: false,
            processor: PublishWeekProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            status: 204
        ),
        new Put(
            uriTemplate: '/shifts/{id}/apply',
            security: 'is_granted(\'ROLE_COURIER\')',
            processor: ShiftApplicationProcessor::class,
            denormalizationContext: ['groups' => ['shift_apply']]
        ),
        new Put(
            uriTemplate: '/shifts/{id}/unapply',
            security: 'is_granted(\'ROLE_COURIER\')',
            processor: ShiftApplicationProcessor::class,
            denormalizationContext: ['groups' => ['shift_apply']]
        ),
        new Put(
            uriTemplate: '/shifts/{id}/report_time',
            input: ShiftTimeReportInput::class,
            processor: ShiftTimeReportProcessor::class,
            // Assignees report their own hours, dispatchers anyone's;
            // the processor enforces the self-only rule for couriers
            security: 'is_granted(\'ROLE_COURIER\') or is_granted(\'ROLE_DISPATCHER\')',
            denormalizationContext: ['groups' => ['shift_time_report']]
        ),
    ],
    normalizationContext: ['groups' => ['shift']],
    denormalizationContext: ['groups' => ['shift_create']]
)]
#[ApiFilter(filterClass: ShiftDateFilter::class, properties: ['date'])]
class Shift
{
    const TYPE_DRIVE = 'drive';
    const TYPE_DISPATCH = 'dispatch';
    const TYPE_ADMIN = 'admin';

    #[Groups(['shift'])]
    protected $id;

    #[Groups(['shift', 'shift_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    protected ?string $type = null;

    #[Groups(['shift', 'shift_create'])]
    #[Assert\NotBlank]
    protected ?\DateTime $startsAt = null;

    #[Groups(['shift', 'shift_create'])]
    #[Assert\NotBlank]
    #[Assert\GreaterThan(propertyPath: 'startsAt')]
    protected ?\DateTime $endsAt = null;

    #[Groups(['shift', 'shift_create'])]
    #[Assert\Range(min: 1)]
    protected int $slots = 1;

    #[Groups(['shift', 'shift_create'])]
    #[Assert\Range(min: 0)]
    protected int $breakMinutes = 0;

    #[Groups(['shift', 'shift_create'])]
    #[Assert\Length(max: 65535)]
    protected ?string $comment = null;

    protected ?UserInterface $createdBy = null;

    #[Groups(['shift'])]
    protected Collection $assignments;

    /**
     * Skills a person must have to be a good fit for this shift. Informational
     * — assigning someone without a required skill warns, never blocks.
     *
     * @var Collection<int, Skill>
     */
    #[Groups(['shift', 'shift_create'])]
    protected Collection $requiredSkills;

    /**
     * Users queuing for a spot once the shift is full, ordered first come
     * first served (see ShiftApplicationManager).
     *
     * @var Collection<int, ShiftWaitlistEntry>
     */
    #[Groups(['shift'])]
    protected Collection $waitlist;

    /**
     * Virtual property (not persisted) used to assign users on create/update.
     *
     * @var User[]|null
     */
    #[Groups(['shift_create'])]
    protected ?array $users = null;

    protected $createdAt;

    protected $updatedAt;

    public function __construct()
    {
        $this->assignments = new ArrayCollection();
        $this->requiredSkills = new ArrayCollection();
        $this->waitlist = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStartsAt(): ?\DateTime
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTime $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTime
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTime $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getSlots(): int
    {
        return $this->slots;
    }

    public function setSlots(int $slots): self
    {
        $this->slots = $slots;

        return $this;
    }

    public function getBreakMinutes(): int
    {
        return $this->breakMinutes;
    }

    public function setBreakMinutes(int $breakMinutes): self
    {
        $this->breakMinutes = $breakMinutes;

        return $this;
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

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserInterface $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(ShiftAssignment $assignment): self
    {
        if (!$this->assignments->contains($assignment)) {
            $assignment->setShift($this);
            $this->assignments->add($assignment);
        }

        return $this;
    }

    public function removeAssignment(ShiftAssignment $assignment): self
    {
        $this->assignments->removeElement($assignment);

        return $this;
    }

    /**
     * @return UserInterface[]
     */
    public function getAssignedUsers(): array
    {
        return array_values($this->assignments->map(fn (ShiftAssignment $a) => $a->getUser())->toArray());
    }

    public function isAssigned(UserInterface $user): bool
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->getUser() === $user) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return User[]|null
     */
    public function getUsers(): ?array
    {
        return $this->users;
    }

    /**
     * @param User[]|null $users
     */
    public function setUsers(?array $users): self
    {
        $this->users = $users;

        return $this;
    }

    /**
     * @return Collection<int, ShiftWaitlistEntry>
     */
    public function getWaitlist(): Collection
    {
        return $this->waitlist;
    }

    public function addWaitlistEntry(ShiftWaitlistEntry $entry): self
    {
        if (!$this->waitlist->contains($entry)) {
            $entry->setShift($this);
            $this->waitlist->add($entry);
        }

        return $this;
    }

    public function removeWaitlistEntry(ShiftWaitlistEntry $entry): self
    {
        $this->waitlist->removeElement($entry);

        return $this;
    }

    public function isWaitlisted(UserInterface $user): bool
    {
        foreach ($this->waitlist as $entry) {
            if ($entry->getUser() === $user) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Skill>
     */
    public function getRequiredSkills(): Collection
    {
        return $this->requiredSkills;
    }

    public function addRequiredSkill(Skill $skill): self
    {
        if (!$this->requiredSkills->contains($skill)) {
            $this->requiredSkills->add($skill);
        }

        return $this;
    }

    public function removeRequiredSkill(Skill $skill): self
    {
        $this->requiredSkills->removeElement($skill);

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
