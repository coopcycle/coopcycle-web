<?php

namespace AppBundle\Action\TimeSlot;

use AppBundle\Api\Resource\TimeSlotChoice;
use AppBundle\Api\Resource\TimeSlotChoices;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractTimeSlotChoices
{
    protected $translator;
    protected $country;
    protected $locale;

    public function __construct(
        TranslatorInterface $translator,
        string $country,
        string $locale)
    {
        $this->translator = $translator;
        $this->country = $country;
        $this->locale = $locale;
    }

    protected function createTimeSlotChoices($data)
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