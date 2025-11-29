<?php

namespace AppBundle\Service;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use Doctrine\ORM\EntityManagerInterface;

class TimeSlotManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $country,
    )
    {}

    /**
     * @return mixed[]
     */
    public function getTimeSlotApplications(TimeSlot $timeSlot) {
        /*
            Returns an array of entities related to the t$timeSlot.
        */

        return array_merge(
            $this->getStores($timeSlot),
            $this->getDeliveryForms($timeSlot)
        );
    }

    /**
     * @return Store[]
     */
    public function getStores(TimeSlot $timeSlot) {
        return $this->entityManager
            ->getRepository(Store::class)
            ->createQueryBuilder('s')
            ->innerJoin('s.timeSlots', 'sts', 'WITH', 'sts.store = s')
            ->where('sts.timeSlot = :timeslot')
            ->setParameter('timeslot', $timeSlot)
            ->getQuery()->getResult();
    }

    /**
     * @return DeliveryForm[]]
     */
    public function getDeliveryForms(TimeSlot $timeSlot) {
        return $this->entityManager->getRepository(DeliveryForm::class)->findBy(['timeSlot' => $timeSlot]);
    }

    /**
     * Find a time slot that has a given range among it's choices.
     */
    public function findByRange(Store $store, TsRange $range): TimeSlot|null {
        $storeTimeSlots = $store->getTimeSlots();

        foreach ($storeTimeSlots as $storeTimeSlot) {
            if ($this->isChoice($storeTimeSlot, $range)) {
                return $storeTimeSlot;
            }
        }

        return null;
    }

    /**
     * Check if a time slot has a given range among it's choices.
     */
    public function isChoice(TimeSlot $timeSlot, TsRange $range): bool
    {
        $choiceLoader = new TimeSlotChoiceLoader($timeSlot, $this->country);
        $choiceList = $choiceLoader->loadChoiceList();

        /**
         * @var TimeSlotChoice $choice
         */
        foreach ($choiceList->getChoices() as $choice) {
            if ($choice->equals($range)) {
                return true;
            }
        }

        return false;
    }
}
