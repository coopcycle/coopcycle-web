<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Metadata\ApiResource;
use AppBundle\Entity\Store;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * 'delivery' group is used when serialising a pre-filled formData in twig templates
 */
#[ApiResource(
    types: ['http://schema.org/ParcelDelivery'],
)]
final class DeliveryInputDto
{

    #[Groups(['delivery', 'pricing_deliveries'])]
    public int|null $id = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public Store|null $store = null;

    /**
     * In GET requests: Legacy field for compatibility with the existing API. Use `tasks` instead.
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public TaskDto|null $pickup = null;

    /**
     * In GET requests: Legacy field for compatibility with the existing API. Use `tasks` instead.
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public TaskDto|null $dropoff = null;

    /**
     * @var TaskDto[]|null
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public array|null $tasks = null;

    /**
     * @deprecated Set weight via TaskDto
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public int|null $weight = null;

    /**
     * @deprecated set packages via TaskDto
     * @var TaskPackageDto[]|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $packages = null;

    // used only in a POST request
    #[Groups(['delivery_create'])]
    public string|null $rrule = null;

    // used only in a POST request, alongside `rrule`
    #[Groups(['delivery_create'])]
    public \DateTime|null $rruleStartDate = null;

    // used only in a POST request, alongside `rrule`
    #[Groups(['delivery_create'])]
    public \DateTime|null $rruleEndDate = null;

    #[Groups(['delivery', 'delivery_create', 'pricing_deliveries'])]
    #[Assert\Valid]
    public DeliveryOrderDto|null $order = null;

    #[Assert\Callback]
    public function validateRecurrenceDateRange(ExecutionContextInterface $context): void
    {
        if (is_null($this->rruleStartDate) || is_null($this->rruleEndDate)) {
            return;
        }

        if ($this->rruleEndDate < $this->rruleStartDate) {
            $context->buildViolation('The end date must be after the start date')
                ->atPath('rruleEndDate')
                ->addViolation();
        }
    }
}
