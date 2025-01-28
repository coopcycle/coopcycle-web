<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Api\Dto\InvoiceLineItem;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
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

        $delivery = $order->getDelivery();
        $store = $delivery?->getStore();

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

        if ($delivery && $deliveryItem) {
            $productVariant = $deliveryItem->getVariant();
            $product = $productVariant->getProduct();

            $descriptionParts = [];

            $descriptionParts[] = $product->getName();
            $descriptionParts[] = $productVariant->getName();

            $pickupLabel = $this->translator->trans(sprintf('task.type.%s', Task::TYPE_PICKUP));
            $dropoffLabel = $this->translator->trans(sprintf('task.type.%s', Task::TYPE_DROPOFF));

            if (str_contains($productVariant->getName(), $pickupLabel) || str_contains($productVariant->getName(), $dropoffLabel)) {
                // Added to avoid duplicate task information for orders created during beta testing in January 2025
                // Could be removed after a few months
            } else {
                foreach ($delivery->getTasks() as $task) {
                    $clientName = $task->getAddress()->getName();

                    $descriptionParts[] = sprintf('%s: %s',
                        $this->translator->trans(sprintf('task.type.%s', $task->getType())),
                        $clientName ?: $task->getAddress()->getStreetAddress());
                }
            }

            $descriptionParts[] = Carbon::instance($orderDate)->locale($this->locale)->isoFormat('L');

            $description = sprintf('%s (%s)',
                implode(' - ', $descriptionParts),
                $this->translator->trans('adminDashboard.invoicing.line_item.order_number', [
                    '%number%' => $order->getNumber(),
                ], 'messages')
            );

            // Remove special characters (emojis, etc.) from the description
            // See https://symbl.cc/en/unicode-table/ for a list of Unicode ranges
            $description = preg_replace('/[\x{2600}-\x{27FF}\x{10000}-\x{10FFFF}]/u', '', $description);
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
