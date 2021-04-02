<?php

namespace AppBundle\Action\TimeSlot;

use AppBundle\Api\Resource\TimeSlotChoice;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Security\TokenStoreExtractor;
use Carbon\Carbon;

class Choices
{
    private $storeExtractor;
    private $country;

    public function __construct(
        TokenStoreExtractor $storeExtractor,
        string $country)
    {
        $this->storeExtractor = $storeExtractor;
        $this->country = $country;
    }

    public function __invoke()
    {
        $store = $this->storeExtractor->extractStore();

        $choiceLoader = new TimeSlotChoiceLoader($store->getTimeSlot(), $this->country);
        $choiceList = $choiceLoader->loadChoiceList();

        $choices = [];
        foreach ($choiceList->getChoices() as $choice) {

            $period = $choice->toDatePeriod();

            $value = implode('/', [
                Carbon::instance($period->start)->tz('UTC')->toIso8601ZuluString(),
                Carbon::instance($period->end)->tz('UTC')->toIso8601ZuluString()
            ]);

            $choice = new TimeSlotChoice();
            $choice->value = $value;
            $choice->label = '';

            $choices[] = $choice;
        }

        return $choices;
    }
}
