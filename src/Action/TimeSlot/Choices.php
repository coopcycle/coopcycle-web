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

class Choices
{
    private $storeExtractor;
    private $translator;
    private $country;
    private $locale;

    public function __construct(
        TokenStoreExtractor $storeExtractor,
        TranslatorInterface $translator,
        string $country,
        string $locale)
    {
        $this->storeExtractor = $storeExtractor;
        $this->translator = $translator;
        $this->country = $country;
        $this->locale = $locale;
    }

    public function __invoke()
    {
        $store = $this->storeExtractor->extractStore();

        $choiceLoader = new TimeSlotChoiceLoader($store->getTimeSlot(), $this->country);
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
