<?php

namespace AppBundle\Api\State\Woopit;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Action\Woopit\UpdateDeliveryTrait;
use AppBundle\Entity\Delivery;
use AppBundle\Service\Geocoder;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use libphonenumber\PhoneNumberUtil;

class UpdateProcessor implements ProcessorInterface
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

    /**
     * @param Delivery $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $delivery = $this->updateDelivery($data, $uriVariables['deliveryId']);

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();
    }
}
