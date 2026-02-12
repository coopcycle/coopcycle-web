<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShopCollectionInput;
use AppBundle\Entity\LocalBusiness\Collection as ShopCollection;

class ShopCollectionProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $provider,
        private ProcessorInterface $persistProcessor)
    {}

    /**
     * @param ShopCollectionInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $collection = new ShopCollection();
        if ($operation instanceof Put) {
            /** @var ShopCollection */
            $collection = $this->provider->provide($operation, $uriVariables, $context);
            if (!empty($data->shops)) {
                $collection->getItems()->clear();
            }
        }

        if (!empty($data->title)) {
            $collection->setTitle($data->title);
        }

        if (!empty($data->shops)) {
            foreach ($data->shops as $shop) {
                $collection->addShop($shop);
            }
        }

        return $this->persistProcessor->process($collection, $operation, $uriVariables, $context);
    }
}
