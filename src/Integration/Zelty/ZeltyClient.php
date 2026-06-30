<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ZeltyClient
{
    private ?string $authToken = null;

    public function __construct(
        private HttpClientInterface $zeltyClient
    ) {}

    public function setAuth(string $token): void
    {
        $this->authToken = $token;
    }

    private function authOptions(): array
    {
        return $this->authToken !== null ? ['auth_bearer' => $this->authToken] : [];
    }

    public function pushToZelty(OrderInterface $order): void
    {
        $this->zeltyClient->request('POST', 'orders', array_merge($this->authOptions(), [
            'json' => $this->buildOrderPayload($order),
        ]));
    }

    private function buildOrderPayload(OrderInterface $order): array
    {
        $payload = [
            'remote_id'        => (string) $order->getId(),
            'display_id'       => substr($order->getNumber(), 0, 10),
            'fulfillment_type' => 'deliver_by_partner',
            'mode'             => 'delivery',
            'source'           => 'web',
            'due_date'         => $order->getShippedAt()?->format(\DateTime::ATOM),
            'customer'         => $this->buildCustomerPayload($order),
            'address'          => $this->buildAddressPayload($order),
            'items'            => $this->buildItemsPayload($order),
            'total'            => $order->getItemsTotal(),
        ];

        if ($order->getNotes() !== null) {
            $payload['comment'] = substr($order->getNotes(), 0, 256);
        }

        return $payload;
    }

    private function buildCustomerPayload(OrderInterface $order): ?array
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

    private function buildAddressPayload(OrderInterface $order): ?array
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

    private function buildItemsPayload(OrderInterface $order): array
    {
        $items = [];

        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();

            $entry = [
                'remote_id' => $variant?->getCode(),
                'type'      => 'dish',
                'price'     => $item->getUnitPrice(),
            ];

            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $items[] = $entry;
            }
        }

        return $items;
    }

    public function upsertWebhook(string $event, ?string $url): string
    {
        $webhookConfig = $url !== null ? ['target' => $url, 'version' => 'v2'] : null;

        $response = $this->zeltyClient->request('POST', 'webhooks', array_merge($this->authOptions(), [
            'json' => ['webhooks' => [$event => $webhookConfig]],
        ]));

        $data = json_decode($response->getContent(), true);

        return $data['secret_key'];
    }

    public function getTaxes(): array
    {
        $response = $this->zeltyClient->request('GET', 'catalog/taxes', $this->authOptions());
        return json_decode($response->getContent(), true);
    }
}
