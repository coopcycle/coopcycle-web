<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ShiftDispatchSyncInput
{
    /**
     * Any date within the target week; aligned to the Monday.
     */
    #[Groups(['shift_dispatch_sync_create'])]
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $week = null;
}
