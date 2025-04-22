<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\LoopeatFormats;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LoopeatFormatsProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $provider,
        private OrderProcessorInterface $orderProcessor,
        private ProcessorInterface $persistProcessor,
        private LoopEatClient $client,
        private IriConverterInterface $iriConverter)
    {}

    /**
     * @param LoopeatFormats $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var OrderInterface */
        $order = $this->provider->provide($operation, $uriVariables, $context);

        $deliver = [];

        foreach ($data->items as $item) {

            $orderItem = $this->iriConverter->getResourceFromIri($item->orderItem['@id']);

            if (!$order->hasItem($orderItem)) {
                throw new BadRequestHttpException(sprintf('Item #%s does not belong to order #%s', $orderItem->getId(), $order->getId()));
            }

            $deliver[$orderItem->getId()] = array_map(function($format) {
                unset($format['format_name']);

                return $format;
            }, $item->formats);
        }

        $order->setLoopeatDeliver($deliver);

        $this->orderProcessor->process($order);
        $this->client->updateDeliverFormats($order);

        return $this->persistProcessor->process($order, $operation, $uriVariables, $context);
    }
}

