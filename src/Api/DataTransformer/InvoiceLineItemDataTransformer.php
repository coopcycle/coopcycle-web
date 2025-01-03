<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Api\Dto\InvoiceLineItem;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\SettingsManager;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceLineItemDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly SettingsManager $settingsManager,
        private readonly string $locale
    )
    {
    }

    public function transform($object, string $to, array $context = [])
    {
        $order = $object;

        $store = $order->getDelivery()?->getStore();

        $request = $this->requestStack->getCurrentRequest();
        $requestId = $request->headers->get('X-Request-ID');

        $invoiceId = hash('sha256', sprintf('%s-%d',
            $requestId,
            $store?->getId() ?? 0
        ));

        $invoiceDate = new \DateTime();

        $deliveryItem = $order->getDeliveryItem();

        $product = '';

        $description = '';

        $orderDate = $order->getShippingTimeRange()?->getUpper() ?? $order->getCreatedAt();

        if ($deliveryItem) {
            $product = $deliveryItem->getVariant()->getProduct()->getName();

            $description = sprintf('%s - %s - %s (%s)',
                $product,
                $deliveryItem->getVariant()->getName(),
                Carbon::instance($orderDate)->locale($this->locale)->isoFormat('L'),
                $this->translator->trans('adminDashboard.invoicing.line_item.order_number', [
                    '%number%' => $order->getNumber(),
                ], 'messages')
            );
        }

        return new InvoiceLineItem(
            $invoiceId,
            $invoiceDate,
            $store?->getId(),
            $store?->getLegalName() ?? $store?->getName(),
            $this->settingsManager->get('accounting_account') ?? '',
            $product,
            $order->getId(),
            $order->getNumber(),
            $orderDate,
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
