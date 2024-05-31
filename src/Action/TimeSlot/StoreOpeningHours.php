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

class StoreOpeningHours
{
    public function __construct(
        private TranslatorInterface $translator,
        private string $country,
        private string $locale
    ) {}

    public function __invoke($data)
    {

        $choiceLoader = new TimeSlotChoiceLoader($data, $this->country);
        $choiceList = $choiceLoader->loadChoiceList();

        $choices = [];
        foreach ($choiceList->getChoices() as $choice) {
            $period = $choice->toDatePeriod();
            $choices[] = new TimeSlotChoice($period, $this->translator, $this->locale);
        }

        $response = new TimeSlotChoices();
        $response->id = Uuid::uuid4()->toString();
        $response->choices = $choices;


        return $response;
    }
}
