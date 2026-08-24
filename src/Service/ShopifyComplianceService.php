<?php

namespace AppBundle\Service;

use AppBundle\Entity\Address;
use AppBundle\Entity\Shopify\ShopifyOrder;
use AppBundle\Entity\Shopify\ShopifyShop;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles Shopify's three mandatory privacy-law compliance topics.
 *
 * These are app-level webhooks: Shopify posts them to a single URI, which for a
 * multi-tenant install can only be the gateway. The gateway verifies Shopify's
 * HMAC, resolves the shop to its cooperative, and forwards the payload here.
 *
 * Deliveries themselves are never deleted — a cooperative needs its operational
 * and accounting history, and Shopify allows retaining data it is legally
 * required to keep. What is removed is the personal data attached to them.
 */
class ShopifyComplianceService
{
    public const TOPIC_CUSTOMERS_DATA_REQUEST = 'customers/data_request';
    public const TOPIC_CUSTOMERS_REDACT       = 'customers/redact';
    public const TOPIC_SHOP_REDACT            = 'shop/redact';

    public const TOPICS = [
        self::TOPIC_CUSTOMERS_DATA_REQUEST,
        self::TOPIC_CUSTOMERS_REDACT,
        self::TOPIC_SHOP_REDACT,
    ];

    private const REDACTED = '[redacted]';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array the response body handed back to the gateway
     */
    public function handle(string $topic, array $payload): array
    {
        $shopDomain = $payload['shop_domain'] ?? null;

        if (!$shopDomain) {
            throw new \InvalidArgumentException('Missing shop_domain in compliance payload.');
        }

        return match ($topic) {
            self::TOPIC_CUSTOMERS_DATA_REQUEST => $this->onCustomersDataRequest($shopDomain, $payload),
            self::TOPIC_CUSTOMERS_REDACT       => $this->onCustomersRedact($shopDomain, $payload),
            self::TOPIC_SHOP_REDACT            => $this->onShopRedact($shopDomain),
            default => throw new \InvalidArgumentException(sprintf('Unknown compliance topic "%s".', $topic)),
        };
    }

    /**
     * Report the personal data held for a customer. Shopify gives 30 days to get
     * it to the merchant; there is no API to answer through, so the report is
     * logged at a level the cooperative's admins can pick up and forward.
     */
    private function onCustomersDataRequest(string $shopDomain, array $payload): array
    {
        $orders = $this->findOrders($shopDomain, $payload['orders_requested'] ?? null);

        $report = [];
        foreach ($orders as $shopifyOrder) {
            $address = $this->dropoffAddress($shopifyOrder);

            $report[] = [
                'shopify_order_id'   => $shopifyOrder->getShopifyOrderId(),
                'shopify_order_name' => $shopifyOrder->getShopifyOrderName(),
                'delivery_id'        => $shopifyOrder->getDelivery()?->getId(),
                'contact_name'       => $address?->getContactName(),
                'telephone'          => $address?->getTelephone() ? (string) $address->getTelephone() : null,
                'street_address'     => $address?->getStreetAddress(),
            ];
        }

        // Deliberately noisy: a human has to act on this within 30 days.
        $this->logger->warning(
            'Shopify customer data request received — forward this report to the merchant within 30 days.',
            [
                'shop_domain'     => $shopDomain,
                'data_request_id' => $payload['data_request']['id'] ?? null,
                'customer_id'     => $payload['customer']['id'] ?? null,
                'orders'          => $report,
            ]
        );

        return ['status' => 'logged', 'orders_found' => count($report)];
    }

    /**
     * Strip personal data from the deliveries behind the listed orders.
     */
    private function onCustomersRedact(string $shopDomain, array $payload): array
    {
        $orders = $this->findOrders($shopDomain, $payload['orders_to_redact'] ?? null);

        $redacted = 0;
        foreach ($orders as $shopifyOrder) {
            if ($this->redactOrder($shopifyOrder)) {
                $redacted++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info(sprintf(
            'Redacted %d Shopify order(s) for shop %s (customer %s).',
            $redacted,
            $shopDomain,
            $payload['customer']['id'] ?? 'unknown'
        ));

        return ['status' => 'redacted', 'orders_redacted' => $redacted];
    }

    /**
     * Fires 48h after uninstall: drop the shop, its access token and the link to
     * its orders, and strip personal data from every delivery it produced.
     */
    private function onShopRedact(string $shopDomain): array
    {
        $shop = $this->entityManager->getRepository(ShopifyShop::class)
            ->findOneBy(['shopDomain' => $shopDomain]);

        if (!$shop) {
            $this->logger->info(sprintf('shop/redact for unknown shop %s, nothing to do.', $shopDomain));

            return ['status' => 'not_found'];
        }

        $orders = $this->entityManager->getRepository(ShopifyOrder::class)
            ->findBy(['shop' => $shop]);

        $redacted = 0;
        foreach ($orders as $shopifyOrder) {
            if ($this->redactOrder($shopifyOrder)) {
                $redacted++;
            }
            // The delivery survives; only its Shopify linkage goes away.
            $this->entityManager->remove($shopifyOrder);
        }

        $this->entityManager->remove($shop);
        $this->entityManager->flush();

        $this->logger->info(sprintf(
            'Redacted shop %s: removed the shop record and %d order link(s).',
            $shopDomain,
            $redacted
        ));

        return ['status' => 'redacted', 'orders_redacted' => $redacted];
    }

    /**
     * @param array|null $shopifyOrderIds null means "every order of this shop"
     *
     * @return ShopifyOrder[]
     */
    private function findOrders(string $shopDomain, ?array $shopifyOrderIds): array
    {
        $shop = $this->entityManager->getRepository(ShopifyShop::class)
            ->findOneBy(['shopDomain' => $shopDomain]);

        if (!$shop) {
            return [];
        }

        $criteria = ['shop' => $shop];

        // Scoping by shop as well as id matters: order ids are only unique per
        // shop, so a bare id lookup could redact another merchant's delivery.
        if (null !== $shopifyOrderIds) {
            if ([] === $shopifyOrderIds) {
                return [];
            }
            $criteria['shopifyOrderId'] = array_map('strval', $shopifyOrderIds);
        }

        return $this->entityManager->getRepository(ShopifyOrder::class)->findBy($criteria);
    }

    private function redactOrder(ShopifyOrder $shopifyOrder): bool
    {
        $delivery = $shopifyOrder->getDelivery();

        if (!$delivery) {
            return false;
        }

        foreach ($delivery->getTasks() as $task) {
            // The order note is merchant/customer free text — treat it as personal.
            $task->setComments(null);

            $address = $task->getAddress();
            if ($address) {
                $this->redactAddress($address);
            }
        }

        return true;
    }

    /**
     * streetAddress is blanked rather than set to a placeholder on purpose:
     * TaskSubscriber re-geocodes a task's address whenever streetAddress changes
     * from one non-empty value to another, so a placeholder would send
     * "[redacted]" to the geocoder and replace the address with whatever came
     * back. An empty value skips that path.
     *
     * The coordinates are kept because the column is NOT NULL and the
     * cooperative's delivery-distance history is computed from them. They are
     * coarse enough to be useless as an identifier on their own, but if your DPO
     * disagrees this is the place to change it.
     */
    private function redactAddress(Address $address): void
    {
        $address->setContactName(self::REDACTED);
        $address->setTelephone(null);
        $address->setStreetAddress('');
    }

    private function dropoffAddress(ShopifyOrder $shopifyOrder): ?Address
    {
        return $shopifyOrder->getDelivery()?->getDropoff()?->getAddress();
    }
}
