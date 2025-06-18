<?php

namespace AppBundle\Api\Dto;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\TimeSlot;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

// Merge with MyTaskDto in the future
final class TaskDto
{
    // null in POST input data
    #[Groups(['delivery', 'delivery_create'])]
    public int|null $id = null;

    #[Groups(['delivery'])]
    public DateTime|null $createdAt = null;

    #[Groups(['delivery'])]
    public DateTime|null $updatedAt = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public string|null $type = null;

    #[Groups(["delivery"])]
    public string|null $status = null;

    /**
     * FIXME: Ideally, an Address object should be normalized/denormalized by a standard API platform denormalizer,
     * but we can't support both Address object and plain text in the same field
     * until we upgrade to API Platform 3.2?: https://github.com/api-platform/core/pull/5470
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public array|string|null $address = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $latLng = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public DateTime|null $after = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public DateTime|null $before = null;

    /**
     * @deprecated: use $after instead
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public DateTime|null $doneAfter = null;

    /**
     * @deprecated: use $before instead
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public DateTime|null $doneBefore = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public TimeSlot|null $timeSlotUrl = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public TsRange|null $timeSlot = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public string|null $comments = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public array|string|null $tags = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public int|null $weight = null;

    /**
     * @var TaskPackageDto[]|null
     */
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public array|null $packages = null;

    /**
     * FIXME: parse metadata in a separate denormalizer
     */
    // string in POST input data
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public array|string|null $metadata = null;

    /**
     * FIXME: parse username in a separate denormalizer
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $assignedTo = null;

    /**
     * returns an array of key-value pairs; replace with an object in the future?
     * @var array|null
     */
    #[Groups(['delivery'])]
    public array|null $barcode = null;
}
