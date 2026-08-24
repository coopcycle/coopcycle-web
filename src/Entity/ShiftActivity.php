<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use AppBundle\Api\Dto\ShiftActivityInput;
use AppBundle\Api\Dto\ShiftActivityUpdateInput;
use AppBundle\Api\State\ShiftActivityCreateProcessor;
use AppBundle\Api\State\ShiftActivityUpdateProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A kind of shift (e.g. "Delivery", "Dispatch", "Administration", or any
 * dispatcher-defined custom activity). Referenced from Shift/ShiftTemplateShift
 * by its immutable `slug` (a plain string, not an IRI — see Shift::$activity),
 * so the catalog can be edited/extended without ever touching existing shifts.
 * Ships with a small default set (see migration), all editable/deletable
 * except that the `slug` itself never changes once created.
 */
#[ApiResource(
    shortName: 'ShiftActivity',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            // Couriers need this to show a nice label on their own shift
            // cards, not just dispatchers
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
        new Get(security: 'is_granted(\'ROLE_COURIER\')'),
        new Post(
            input: ShiftActivityInput::class,
            processor: ShiftActivityCreateProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Put(
            input: ShiftActivityUpdateInput::class,
            processor: ShiftActivityUpdateProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\')'),
    ],
    normalizationContext: ['groups' => ['shift_activity']],
)]
class ShiftActivity
{
    #[Groups(['shift_activity'])]
    protected $id;

    /**
     * Immutable once set — this is the value stored on every Shift, so
     * changing it would silently detach existing shifts from this activity.
     */
    #[Groups(['shift_activity'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    protected ?string $slug = null;

    #[Groups(['shift_activity'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $label = null;

    /**
     * Hex color (e.g. "#ffadad") shown for this activity across the planning
     * grids. Null falls back to a deterministic color derived from the slug.
     */
    #[Groups(['shift_activity'])]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'This is not a valid hex color.')]
    protected ?string $color = null;

    protected $createdAt;

    protected $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

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
