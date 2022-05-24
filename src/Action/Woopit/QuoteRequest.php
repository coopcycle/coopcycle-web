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
use AppBundle\Service\PriceHelper;
use Carbon\Carbon;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class QuoteRequest
{
    use CreateDeliveryTrait;

    private $tokenExtractor;
    private $deliveryManager;
    private $geocoder;

    public function __construct(
        TokenStoreExtractor $tokenExtractor,
        DeliveryManager $deliveryManager,
        Geocoder $geocoder,
        PriceHelper $priceHelper)
    {
        $this->tokenExtractor = $tokenExtractor;
        $this->deliveryManager = $deliveryManager;
        $this->geocoder = $geocoder;
        $this->priceHelper = $priceHelper;
    }

    public function __invoke(WoopitQuoteRequest $data)
    {
        $store = $this->tokenExtractor->extractStore();

        if (!$store) {
            // TODO Throw Exception
        }

        $delivery = $this->createDelivery($data);

        $pricingRuleSet = $store->getPricingRuleSet();

        $price = $this->deliveryManager->getPrice($delivery, $pricingRuleSet);

        $data->price = $price;
        $data->priceDetails = $this->priceHelper->fromTaxIncludedAmount($price);

        return $data;
    }
}
