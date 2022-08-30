<?php

namespace AppBundle\Action\Woopit;

use AppBundle\Entity\Woopit\QuoteRequest as WoopitQuoteRequest;
use AppBundle\Entity\Store;
use AppBundle\Entity\Woopit\WoopitIntegration;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\PriceHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class QuoteRequest
{
    use CreateDeliveryTrait;

    private $deliveryManager;
    private $geocoder;

    public function __construct(
        DeliveryManager $deliveryManager,
        Geocoder $geocoder,
        PriceHelper $priceHelper,
        EntityManagerInterface $entityManager,
        ValidatorInterface $checkDeliveryValidator)
    {
        $this->deliveryManager = $deliveryManager;
        $this->geocoder = $geocoder;
        $this->priceHelper = $priceHelper;
        $this->entityManager = $entityManager;
        $this->checkDeliveryValidator = $checkDeliveryValidator;
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
                "comments" => sprintf('The store with ID %s does not exist', $data->retailer['store']['id'])
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

        $price = $this->deliveryManager->getPrice($delivery, $pricingRuleSet);

        $data->price = $price;
        $data->priceDetails = $this->priceHelper->fromTaxIncludedAmount($price);

        return $data;
    }
}
