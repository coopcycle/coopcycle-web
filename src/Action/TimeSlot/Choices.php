<?php

namespace AppBundle\Action\TimeSlot;

use AppBundle\Security\TokenStoreExtractor;
use Symfony\Contracts\Translation\TranslatorInterface;

class Choices extends AbstractTimeSlotChoices
{
    private $storeExtractor;

    public function __construct(
        TokenStoreExtractor $storeExtractor,
        TranslatorInterface $translator,
        string $country,
        string $locale)
    {
        parent::__construct($translator, $country, $locale);
        $this->storeExtractor = $storeExtractor;
    }

    public function __invoke()
    {
        $store = $this->storeExtractor->extractStore();
        return $this->createTimeSlotChoices($store->getTimeSlot());
    }
}