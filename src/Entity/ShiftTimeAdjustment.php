<?php

namespace AppBundle\Entity;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The hours actually worked on a shift assignment, when they differ from the
 * plan (overtime, leaving early, longer break, …). Reported after the fact by
 * the employee or a dispatcher — this is declarative, not a clock-in/out.
 *
 * The planned shift is never modified: payroll/compliance reads the adjusted
 * interval when one exists, the planning keeps showing the plan.
 */
class ShiftTimeAdjustment
{
    protected $id;

    protected ?ShiftAssignment $assignment = null;

    #[Groups(['shift'])]
    protected ?\DateTime $startsAt = null;

    #[Groups(['shift'])]
    protected ?\DateTime $endsAt = null;

    #[Groups(['shift'])]
    protected int $breakMinutes = 0;

    #[Groups(['shift'])]
    protected ?string $comment = null;

    #[Groups(['shift'])]
    protected ?UserInterface $reportedBy = null;

    protected $createdAt;

    #[Groups(['shift'])]
    protected $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssignment(): ?ShiftAssignment
    {
        return $this->assignment;
    }

    public function setAssignment(ShiftAssignment $assignment): self
    {
        $this->assignment = $assignment;

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

    public function getReportedBy(): ?UserInterface
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?UserInterface $reportedBy): self
    {
        $this->reportedBy = $reportedBy;

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
