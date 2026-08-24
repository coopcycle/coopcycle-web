<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\ShiftTemplateApplyInput;
use AppBundle\Api\Dto\ShiftTemplateCreateInput;
use AppBundle\Api\Resource\ShiftTemplateApplyResult;
use AppBundle\Api\State\ShiftTemplateApplyProcessor;
use AppBundle\Api\State\ShiftTemplateCreateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A reusable, named shift pattern ("shape"): a set of (day of week, start
 * time, end time, slots, ...) lines, optionally with the assignees who had
 * those shifts when the template was saved. Applying a template to a target
 * week creates real Shift entities for that week — see
 * ShiftManager::applyTemplate().
 */
#[ApiResource(
    shortName: 'ShiftTemplate',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Post(
            input: ShiftTemplateCreateInput::class,
            processor: ShiftTemplateCreateProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Post(
            uriTemplate: '/shift_templates/{id}/apply',
            input: ShiftTemplateApplyInput::class,
            output: ShiftTemplateApplyResult::class,
            processor: ShiftTemplateApplyProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            status: 201,
            normalizationContext: ['groups' => ['shift_template_apply']],
            denormalizationContext: ['groups' => ['shift_template_apply']]
        ),
    ],
    normalizationContext: ['groups' => ['shift_template']],
    denormalizationContext: ['groups' => ['shift_template_create']]
)]
class ShiftTemplate
{
    #[Groups(['shift_template'])]
    protected $id;

    #[Groups(['shift_template', 'shift_template_create'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name = null;

    protected ?UserInterface $createdBy = null;

    /**
     * @var Collection<int, ShiftTemplateShift>
     */
    #[Groups(['shift_template'])]
    protected Collection $shifts;

    protected $createdAt;

    public function __construct()
    {
        $this->shifts = new ArrayCollection();
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

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?UserInterface $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, ShiftTemplateShift>
     */
    public function getShifts(): Collection
    {
        return $this->shifts;
    }

    public function addShift(ShiftTemplateShift $shift): self
    {
        if (!$this->shifts->contains($shift)) {
            $shift->setTemplate($this);
            $this->shifts->add($shift);
        }

        return $this;
    }

    /**
     * Number of shift lines in this template — shown in the "Load template"
     * list so a dispatcher can tell templates apart without opening each one.
     */
    #[Groups(['shift_template'])]
    public function getShiftCount(): int
    {
        return $this->shifts->count();
    }

    /**
     * Whether this template carries assignee snapshots (some shift lines
     * might, others might not, if built from a partially-staffed week).
     */
    #[Groups(['shift_template'])]
    #[SerializedName('hasAssignees')]
    public function hasAssignees(): bool
    {
        foreach ($this->shifts as $shift) {
            if ($shift->getAssignedUsers()->count() > 0) {
                return true;
            }
        }

        return false;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
