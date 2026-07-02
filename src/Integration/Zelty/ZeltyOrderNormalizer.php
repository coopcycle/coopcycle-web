<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\Customer;
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
            'due_date'         => $order->getPickupExpectedAt()?->format(\DateTime::ATOM),
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
        /** @var Customer|null $customer */
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
            'name'     => $address->getName() ?: $address->getContactName() ?: $address->getStreetAddress(),
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
            $isMenu  = str_starts_with($product?->getZeltyId() ?? '', 'ZM');

            $entry = [
                'id'        => (int) $product?->getZeltyInternalId(),
                'remote_id' => $variant?->getCode(),
                'type'      => $isMenu ? 'menu' : 'dish',
                'price'     => $item->getUnitPrice(),
            ];

            if ($variant !== null) {
                if ($isMenu) {
                    $dishes = $this->normalizeMenuDishes($variant);
                    if ($dishes) {
                        $entry['dishes'] = $dishes;
                    }
                } else {
                    $modifiers = $this->normalizeModifiers($variant);
                    if ($modifiers) {
                        $entry['modifiers'] = $modifiers;
                    }
                }
            }

            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $items[] = $entry;
            }
        }

        return $items;
    }

    private function normalizeMenuDishes(mixed $variant): array
    {
        $dishes = [];
        foreach ($variant->getOptionValues() as $optionValue) {
            $zeltyId = $optionValue->getZeltyId();
            if (!str_starts_with($zeltyId ?? '', 'ZD')) {
                continue;
            }
            $dishes[] = [
                'id_part' => (int) str_replace('ZMP', '', $optionValue->getOption()->getCode()),
                'id'      => (int) str_replace('ZD', '', $zeltyId),
            ];
        }
        return $dishes;
    }

    private function normalizeModifiers(mixed $variant): array
    {
        $modifiers = [];
        foreach ($variant->getOptionValues() as $optionValue) {
            if (!$optionValue->getZeltyInternalId()) {
                continue;
            }
            $optionCode = $optionValue->getOption()?->getCode() ?? '';
            $modifiers[] = [
                'option_id'       => $this->extractOptionId($optionCode),
                'option_value_id' => (int) $optionValue->getZeltyInternalId(),
                'quantity'        => 1,
                'price'           => $optionValue->getPrice() ?? 0,
            ];
        }
        return $modifiers;
    }

    private function extractOptionId(string $optionCode): int
    {
        // Code format: ZO{zeltyOptionId}_{restaurantId}
        if (preg_match('/^ZO(\d+)_\d+$/', $optionCode, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
}
