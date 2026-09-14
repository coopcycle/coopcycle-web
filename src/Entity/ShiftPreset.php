<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\ShiftPresetCreateInput;
use AppBundle\Api\State\ShiftPresetCreateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A reusable, named shape for a *single* shift (activity, time of day, slots,
 * break, required skills, ...) — never assignees. Saved from an existing
 * shift in the planning UI (ShiftModal's "Save as template") and picked back
 * from the "New shift from template" gallery to prefill a new shift on
 * whatever day/week the dispatcher is currently looking at.
 *
 * Deliberately distinct from ShiftTemplate, which snapshots a whole week's
 * worth of shifts (with assignees) to replay onto another week.
 */
#[ApiResource(
    shortName: 'ShiftPreset',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Post(
            input: ShiftPresetCreateInput::class,
            processor: ShiftPresetCreateProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\')'),
    ],
    normalizationContext: ['groups' => ['shift_preset']],
    denormalizationContext: ['groups' => ['shift_preset_create']]
)]
class ShiftPreset
{
    #[Groups(['shift_preset'])]
    protected $id;

    #[Groups(['shift_preset', 'shift_preset_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name = null;

    /**
     * The slug of a ShiftActivity, like Shift::$activity.
     */
    #[Groups(['shift_preset', 'shift_preset_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    protected ?string $activity = null;

    // Time-of-day only. Not directly (de)normalized — ShiftPresetCreateInput
    // carries "HH:MM" strings on create, converted by ShiftPresetCreateProcessor;
    // see getStartTimeLabel()/getEndTimeLabel() below for how they're read back.
    protected ?\DateTime $startTime = null;

    protected ?\DateTime $endTime = null;

    #[Groups(['shift_preset', 'shift_preset_create'])]
    #[Assert\Range(min: 1)]
    protected int $slots = 1;

    #[Groups(['shift_preset', 'shift_preset_create'])]
    #[Assert\Range(min: 0)]
    protected int $breakMinutes = 0;

    #[Groups(['shift_preset', 'shift_preset_create'])]
    #[Assert\Length(max: 65535)]
    protected ?string $comment = null;

    /**
     * @var Collection<int, Skill>
     */
    #[Groups(['shift_preset', 'shift_preset_create'])]
    protected Collection $requiredSkills;

    protected ?UserInterface $createdBy = null;

    protected $createdAt;

    public function __construct()
    {
        $this->requiredSkills = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getActivity(): ?string
    {
        return $this->activity;
    }

    public function setActivity(string $activity): self
    {
        $this->activity = $activity;

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
     * Time-of-day only, as "HH:MM" — see ShiftTemplateShift::getStartTimeLabel()
     * for why the raw \DateTime getters above are kept internal.
     */
    #[Groups(['shift_preset'])]
    #[SerializedName('startTime')]
    public function getStartTimeLabel(): ?string
    {
        return $this->startTime?->format('H:i');
    }

    #[Groups(['shift_preset'])]
    #[SerializedName('endTime')]
    public function getEndTimeLabel(): ?string
    {
        return $this->endTime?->format('H:i');
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

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserInterface $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
