<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class DeliveryRequest
{
    use CreateDeliveryTrait;

    private $tokenExtractor;
    private $deliveryManager;
    private $geocoder;

    public function __construct(
        TokenStoreExtractor $tokenExtractor,
        DeliveryManager $deliveryManager,
        Geocoder $geocoder,
        Hashids $hashids12,
        EntityManagerInterface $entityManager)
    {
        $this->tokenExtractor = $tokenExtractor;
        $this->deliveryManager = $deliveryManager;
        $this->geocoder = $geocoder;
        $this->hashids12 = $hashids12;
        $this->entityManager = $entityManager;
    }

    public function __invoke(WoopitQuoteRequest $data)
    {
        $store = $this->tokenExtractor->extractStore();

        if (!$store) {
            // TODO Throw Exception
        }

        $decoded = $this->hashids12->decode($data->quoteId);

        if (count($decoded) !== 1) {
            // TODO Throw Exception (Not Found)
        }

        $id = current($decoded);

        $quoteRequest = $this->entityManager->getRepository(WoopitQuoteRequest::class)->find($id);

        if (!$quoteRequest) {
             // TODO Throw Exception (Not Found)
        }

        $delivery = $this->createDelivery($data);

        $store->addDelivery($delivery);

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        $data->deliveryObject = $delivery;
        $data->state = WoopitQuoteRequest::STATE_CONFIRMED;

        return $data;
    }
}
