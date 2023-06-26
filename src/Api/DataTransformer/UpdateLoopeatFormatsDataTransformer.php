<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\LoopeatFormats;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
                throw new BadRequestHttpException(sprintf('Could not find item #%s', $itemId));
            }
            if (!$order->hasItem($orderItem)) {
                throw new BadRequestHttpException(sprintf('Item #%s does not belong to order #%s', $itemId, $order->getId()));
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
