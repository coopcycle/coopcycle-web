<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Store;
use AppBundle\Entity\Woopit\WoopitIntegration;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\PriceHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class QuoteRequest
{
    use CreateDeliveryTrait;

    public function __construct(
        private readonly PricingManager $pricingManager,
        private readonly Geocoder $geocoder,
        private readonly PriceHelper $priceHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $checkDeliveryValidator)
    {
    }

    public function __invoke(WoopitQuoteRequest $data)
    {
        $integration = $this->entityManager->getRepository(WoopitIntegration::class)
            ->findOneBy([
                'woopitStoreId' => $data->retailer['store']['id']
            ]);

        if (!$integration) {
            return new JsonResponse([
                "reasons" => [
                    "REFUSED_EXCEPTION"
                ],
                "comment" => sprintf('The store with ID "%s" does not exist', $data->retailer['store']['id'])
            ], 202);
        }

        $store = $this->entityManager->getRepository(Store::class)
            ->find($integration->getStore()->getId());

        if (!$store) {
            // TODO Throw Exception ??
        }

        $delivery = $this->createDelivery($data, $integration);

        $violation = $this->validateDeliveryWithIntegrationConstraints($data, $delivery, $integration);

        if (null !== $violation) {
            return $violation;
        }

        $pricingRuleSet = $store->getPricingRuleSet();

        $price = $this->pricingManager->getPrice($delivery, $pricingRuleSet);

        if (null === $price) {
            return new JsonResponse([
                'reasons' => [
                    'REFUSED_EXCEPTION'
                ],
                'comment' => 'Price could not be calculated'
            ], 400);
        }

        $data->price = $price;
        $data->priceDetails = $this->priceHelper->fromTaxIncludedAmount($price);

        $numberFormatter = \NumberFormatter::create('fr', \NumberFormatter::DECIMAL);
        $numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        $data->priceDetails = array_map(function ($value) use ($numberFormatter) {
            return is_int($value) ? (float) $numberFormatter->format($value / 100, \NumberFormatter::TYPE_DOUBLE) : $value;
        }, $data->priceDetails);

        return $data;
    }
}
