<?php

namespace AppBundle\Service;

use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Store;
use AppBundle\Entity\TimeSlot;
use Doctrine\ORM\EntityManagerInterface;


class TimeSlotManager
{
    public function __construct(private EntityManagerInterface $entityManager)
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
}