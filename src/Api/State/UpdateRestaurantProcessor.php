<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\LocalBusiness;

class UpdateRestaurantProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly ProcessorInterface $persistProcessor,
    )
    {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var LocalBusiness */
        $restaurant = $this->provider->provide($operation, $uriVariables, $context);

        if (is_object($data->hasMenu)) {
            $restaurant->setMenuTaxon($data->hasMenu);
        }

        if (!empty($data->state)) {
            $restaurant->setState($data->state);
        }

        return $this->persistProcessor->process($restaurant, $operation, $uriVariables, $context);
    }
}
