<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Entity\TaskImage;
use AppBundle\Entity\TimeSlot;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Nucleos\UserBundle\Model\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

final class TaskInput
{
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $type = null;

    /**
     * FIXME: Ideally, an Address object should be denormalized by a standard API platform denormalizer,
     * but for now we are doing it manually
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|string|null $address = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $latLng = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $after = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $before = null;

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
    public string|null $timeSlot = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $comments = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|string|null $tags = null;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public int|null $weight = null;

    /**
     * @var TaskPackageInput[]|null
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public array|null $packages = null;

    /**
     * FIXME: parse metadata in a separate denormalizer
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $metadata = null;

    /**
     * FIXME: parse username in a separate denormalizer
     */
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string|null $assignedTo = null;
}
