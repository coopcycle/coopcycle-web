<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Service\Geocoder;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use libphonenumber\PhoneNumberUtil;

class DeliveryUpdate
{
    /**
     * @var \AppBundle\Service\Geocoder
     */
    public $geocoder;
    /**
     * @var \Hashids\Hashids
     */
    public $hashids12;
    public $entityManager;
    /**
     * @var \libphonenumber\PhoneNumberUtil
     */
    public $phoneNumberUtil;
    use UpdateDeliveryTrait;

    public function __construct(
        Geocoder $geocoder,
        Hashids $hashids12,
        EntityManagerInterface $entityManager,
        PhoneNumberUtil $phoneNumberUtil)
    {
        $this->geocoder = $geocoder;
        $this->hashids12 = $hashids12;
        $this->entityManager = $entityManager;
        $this->phoneNumberUtil = $phoneNumberUtil;
    }

    public function __invoke(WoopitQuoteRequest $data, $deliveryId)
    {
        $delivery = $this->updateDelivery($data, $deliveryId);

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        return $data;
    }
}
