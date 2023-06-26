<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\LoopeatFormats;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UpdateLoopeatFormatsDataTransformer implements DataTransformerInterface
{
    public function __construct(private IriConverterInterface $iriConverter)
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

            $orderItem = $this->iriConverter->getItemFromIri($item->orderItem['@id']);

            if (!$order->hasItem($orderItem)) {
                throw new BadRequestHttpException(sprintf('Item #%s does not belong to order #%s', $orderItem->getId(), $order->getId()));
            }

            $deliver[$orderItem->getId()] = array_map(function($format) {
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
