<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * One shift "line" of a ShiftTemplate: a day-of-week + time-of-day pattern,
 * relative to whatever Monday the template gets applied to (see
 * ShiftManager::applyTemplate()).
 */
class ShiftTemplateShift
{
    protected $id;

    protected ?ShiftTemplate $template = null;

    #[Groups(['shift_template'])]
    protected ?string $type = null;

    /**
     * ISO day of week, 1 (Monday) to 7 (Sunday).
     */
    #[Groups(['shift_template'])]
    protected int $dayOfWeek = 1;

    protected ?\DateTime $startTime = null;

    protected ?\DateTime $endTime = null;

    #[Groups(['shift_template'])]
    protected int $slots = 1;

    #[Groups(['shift_template'])]
    protected int $breakMinutes = 0;

    #[Groups(['shift_template'])]
    protected ?string $comment = null;

    /**
     * @var Collection<int, Skill>
     */
    #[Groups(['shift_template'])]
    protected Collection $requiredSkills;

    /**
     * Assignees snapshotted when the template was saved (only populated when
     * "include assignees" was checked at save time).
     *
     * @var Collection<int, User>
     */
    protected Collection $assignedUsers;

    public function __construct()
    {
        $this->requiredSkills = new ArrayCollection();
        $this->assignedUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplate(): ?ShiftTemplate
    {
        return $this->template;
    }

    public function setTemplate(ShiftTemplate $template): self
    {
        $this->template = $template;

        return $this;
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
     * Time-of-day only, as "HH:MM" — the raw \DateTime getters above carry an
     * arbitrary date and are only meant for internal use (ShiftManager); the
     * API must never expose a full datetime for what is conceptually just a
     * wall-clock time; Doctrine's "time" DBAL type round-trips it through a
     * fixed 1970-01-01 date whose DST rules can differ from the real shift's,
     * which would silently shift the hour by one for a naive datetime string.
     */
    #[Groups(['shift_template'])]
    #[SerializedName('startTime')]
    public function getStartTimeLabel(): ?string
    {
        return $this->startTime?->format('H:i');
    }

    #[Groups(['shift_template'])]
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

    /**
     * @return Collection<int, User>
     */
    public function getAssignedUsers(): Collection
    {
        return $this->assignedUsers;
    }

    public function addAssignedUser(User $user): self
    {
        if (!$this->assignedUsers->contains($user)) {
            $this->assignedUsers->add($user);
        }

        return $this;
    }
}
