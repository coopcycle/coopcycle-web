<?php

namespace AppBundle\Action\TimeSlot;

use AppBundle\Api\Resource\TimeSlotChoice;
use AppBundle\Api\Resource\TimeSlotChoices;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Security\TokenStoreExtractor;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class StoreTimeSlots
{
    public function __invoke($data)
    {
        return $data->getTimeSlots();
    }
}
