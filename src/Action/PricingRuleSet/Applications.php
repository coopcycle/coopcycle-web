<?php

namespace AppBundle\Action\PricingRuleSet;

use AppBundle\Api\Dto\ResourceApplication;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Service\PricingRuleSetManager;

class Applications
{
    public function __construct(
        protected PricingRuleSetManager $pricingRuleSetManager,
    )
    {}

    public function __invoke(PricingRuleSet $data)
    {
        return array_map(
            fn ($object) => new ResourceApplication($object),
            $this->pricingRuleSetManager->getPricingRuleSetApplications($data)
        );
    }
}
