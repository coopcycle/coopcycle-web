<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\LoopeatFormats;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;

class UpdateLoopeatFormatsDataTransformer implements DataTransformerInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $order = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];

        $deliver = [];

        foreach ($data->items as $item) {

            // TODO Manage this via API / iriConverter
            preg_match('#\/api\/orders\/(?<orderId>[0-9]+)\/items\/(?<itemId>[0-9]+)#',
                $item->orderItem['@id'], $matches);

            $itemId = $matches['itemId'];

            $orderItem = $this->entityManager->getRepository(OrderItem::class)->find($itemId);

            if (null === $orderItem) {
                // TODO Throw 400
            }
            if (!$order->hasItem($orderItem)) {
                // TODO Throw 400
            }

            $deliver[$itemId] = array_map(function($format) {
                unset($format['format_name']);

                return $format;
            }, $item->formats);
        }

        $order->setLoopeatDeliver($deliver);

        return $order;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Order) {
            return false;
        }

        return $to === Order::class && ($context['input']['class'] ?? null) === LoopeatFormats::class;
    }
}
