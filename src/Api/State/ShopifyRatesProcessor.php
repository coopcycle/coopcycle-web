<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Resource\ShopifyRates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Entity\Task;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;

class ShopifyRatesProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Geocoder $geocoder,
        private DeliveryManager $deliveryManager,
        private PricingManager $pricingManager,
        private CurrencyContextInterface $currencyContext,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param ShopifyRates $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShopifyRates
    {
        $shop = $this->entityManager->getRepository(ShopifyShop::class)->find($data->id);

        if (!$shop || !$shop->getStore()) {
            $data->rates = [];

            return $data;
        }

        $rate = $data->rate;
        $destination = $rate['destination'] ?? null;
        $origin = $rate['origin'] ?? null;

        if (!$destination || !$origin) {
            $data->rates = [];

            return $data;
        }

        $delivery = $this->buildDeliveryForQuote($origin, $destination);

        if (!$delivery) {
            $data->rates = [];

            return $data;
        }

        $store = $shop->getStore();
        $this->deliveryManager->setDefaults($delivery);

        $amount = null;
        if ($store->getPricingRuleSet()) {
            $amount = $this->pricingManager->getPrice($delivery, $store->getPricingRuleSet());
        }

        if (null === $amount) {
            $data->rates = [];

            return $data;
        }

        $currency = $this->currencyContext->getCurrencyCode();

        $data->rates = [
            [
                'service_name'    => 'CoopCycle Bike Delivery',
                'service_code'    => 'coopcycle_bike',
                'total_price'     => (string) $amount,
                'currency'        => $currency,
                'min_delivery_date' => (new \DateTime('+1 hour'))->format(\DateTimeInterface::ATOM),
                'max_delivery_date' => (new \DateTime('+4 hours'))->format(\DateTimeInterface::ATOM),
                'description'     => 'Eco-friendly bike delivery by local couriers',
                'phone_required'  => false,
            ],
        ];

        return $data;
    }

    private function buildDeliveryForQuote(array $origin, array $destination): ?Delivery
    {
        $pickupStreet = sprintf('%s, %s %s',
            $origin['address1'] ?? '',
            $origin['postal_code'] ?? '',
            $origin['city'] ?? ''
        );

        $dropoffStreet = sprintf('%s, %s %s',
            $destination['address1'] ?? '',
            $destination['postal_code'] ?? '',
            $destination['city'] ?? ''
        );

        $pickupAddress  = $this->geocoder->geocode($pickupStreet);
        $dropoffAddress = $this->geocoder->geocode($dropoffStreet);

        if (!$pickupAddress || !$dropoffAddress) {
            return null;
        }

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);
        $pickup->setAddress($pickupAddress);

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff->setAddress($dropoffAddress);

        return Delivery::createWithTasks($pickup, $dropoff);
    }
}
