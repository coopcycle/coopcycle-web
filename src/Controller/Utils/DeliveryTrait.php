<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Service\DeliveryManager;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    protected function getDeliveryPrice(Delivery $delivery, ?PricingRuleSet $pricingRuleSet, DeliveryManager $deliveryManager)
    {
        $price = $deliveryManager->getPrice($delivery, $pricingRuleSet);

        if (null === $price) {
            throw new NoRuleMatchedException();
        }

        return (int) ($price);
    }
}
