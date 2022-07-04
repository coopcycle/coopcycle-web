<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Service\Geocoder;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;

class DeliveryUpdate
{
    use UpdateDeliveryTrait;

    public function __construct(
        Geocoder $geocoder,
        Hashids $hashids12,
        EntityManagerInterface $entityManager)
    {
        $this->geocoder = $geocoder;
        $this->hashids12 = $hashids12;
        $this->entityManager = $entityManager;
    }

    public function __invoke(WoopitQuoteRequest $data, $deliveryId)
    {
        $delivery = $this->updateDelivery($data, $deliveryId);

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        return $data;
    }
}
