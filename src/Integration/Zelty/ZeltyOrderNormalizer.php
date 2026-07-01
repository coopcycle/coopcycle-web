<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ZeltyOrderNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        /** @var OrderInterface $order */
        $order = $object;

        $payload = [
            'remote_id'        => (string) $order->getId(),
            'display_id'       => substr($order->getNumber(), 0, 10),
            'fulfillment_type' => 'deliver_by_partner',
            'mode'             => 'delivery',
            'source'           => 'web',
            'due_date'         => $order->getShippedAt()?->format(\DateTime::ATOM),
            'customer'         => $this->normalizeCustomer($order),
            'address'          => $this->normalizeAddress($order),
            'items'            => $this->normalizeItems($order),
            'total'            => $order->getItemsTotal(),
        ];

        if ($order->getNotes() !== null) {
            $payload['comment'] = substr($order->getNotes(), 0, 256);
        }

        return $payload;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof OrderInterface;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [OrderInterface::class => true];
    }

    private function normalizeCustomer(OrderInterface $order): ?array
    {
        $customer = $order->getCustomer();
        if ($customer === null) {
            return null;
        }

        return [
            'remote_id' => (string) $customer->getId(),
            'fname'     => $customer->getFirstName(),
            'name'      => $customer->getLastName(),
            'mail'      => $customer->getEmail(),
            'phone'     => $customer->getTelephone(),
        ];
    }

    private function normalizeAddress(OrderInterface $order): ?array
    {
        $address = $order->getShippingAddress();
        if ($address === null) {
            return null;
        }

        return [
            'name'     => $address->getName() ?? $address->getContactName() ?? $address->getStreetAddress(),
            'street'   => $address->getStreetAddress(),
            'zip_code' => $address->getPostalCode(),
            'city'     => $address->getAddressLocality(),
        ];
    }

    private function normalizeItems(OrderInterface $order): array
    {
        $items = [];

        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            $product = $variant?->getProduct();

            $entry = [
                'id'        => $product?->getZeltyInternalId(),
                'remote_id' => $variant?->getCode(),
                'type'      => 'dish',
                'price'     => $item->getUnitPrice(),
            ];

            if ($variant !== null) {
                $modifiers = [];
                foreach ($variant->getOptionValues() as $optionValue) {
                    if ($optionValue->getZeltyInternalId()) {
                        $modifiers[] = [
                            'id'    => $optionValue->getZeltyInternalId(),
                            'price' => $optionValue->getPrice() ?? 0,
                        ];
                    }
                }
                if ($modifiers) {
                    $entry['modifiers'] = $modifiers;
                }
            }

            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $items[] = $entry;
            }
        }

        return $items;
    }
}
