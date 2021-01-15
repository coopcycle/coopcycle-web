<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Api\Resource\Pricing as PricingResource;
use AppBundle\Entity\Delivery;
use AppBundle\Service\DeliveryManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Pricing
{
	public function __construct(DeliveryManager $deliveryManager)
    {
        $this->deliveryManager = $deliveryManager;
    }

    public function __invoke(Delivery $data)
    {
        $price = $this->deliveryManager->getPrice($data, $data->getStore()->getPricingRuleSet());

        if (null === $price) {
            throw new BadRequestHttpException('Price could not be calculated');
        }

        $resource = new PricingResource();
        $resource->price = $price;

        return $resource;
    }
}
