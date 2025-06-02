<?php

namespace AppBundle\Api\Dto;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\TimeSlot;
use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

final class TaskDto
{
    // null in POST input data
    #[Groups(['delivery', 'delivery_create'])]
    public int|null $id = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public string|null $type = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public Address|string|null $address = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $latLng = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public DateTime|string|null $after = null;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public DateTime|string|null $before = null;

    /**
     * @deprecated: use $after instead
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $doneAfter = null;

    /**
     * @deprecated: use $before instead
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $doneBefore = null;

    //FIXME: set type to TimeSlot?
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
}
