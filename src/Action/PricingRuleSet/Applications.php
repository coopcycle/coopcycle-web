<?php

namespace AppBundle\Action\PricingRuleSet;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Serializer\PricingRuleSetApplicationsNormalizer;
use AppBundle\Service\PricingRuleSetManager;


class Applications
{
    public function __construct(
        protected PricingRuleSetManager $pricingRuleSetManager,
        protected PricingRuleSetApplicationsNormalizer $normalizer
    )
    {}

    public function __invoke(PricingRuleSet $data)
    {
        return $this->pricingRuleSetManager->getPricingRuleSetApplications($data);
    }
}
