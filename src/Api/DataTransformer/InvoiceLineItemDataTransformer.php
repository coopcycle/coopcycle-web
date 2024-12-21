<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Api\Dto\InvoiceLineItem;
use AppBundle\Entity\Sylius\Order;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceLineItemDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator
    )
    {
    }


    public function transform($object, string $to, array $context = [])
    {
        $order = $object;

        $deliveryItem = $order->getDeliveryItem();

        $description = '';

        if ($deliveryItem) {
            $description = sprintf('%s - %s (%s)',
                $deliveryItem->getVariant()->getProduct()->getName(),
                $deliveryItem->getVariant()->getName(),
                $this->translator->trans('adminDashboard.invoicing.line_item.order_number', [
                    '%number%' => $order->getNumber(),
                ], 'messages')
            );
        }

        return new InvoiceLineItem(
            $order->getDelivery()?->getStore()?->getId(),
            $order->getId(),
            $order->getNumber(),
            $order->getShippingTimeRange()?->getUpper() ?? $order->getCreatedAt(),
            $description,
            $order->getTotal() - $order->getTaxTotal(),
            $order->getTaxTotal(),
            $order->getTotal()
        );
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return InvoiceLineItem::class === $to && $data instanceof Order;
    }

}
