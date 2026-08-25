<?php

namespace AppBundle\MessageHandler;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Shopify\ShopifyOrder;
use AppBundle\Message\ShopifyWebhook;
use AppBundle\Service\ShopifyClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ShopifyWebhookHandler
{
    public function __construct(
        private IriConverterInterface $iriConverter,
        private EntityManagerInterface $entityManager,
        private ShopifyClient $shopifyClient,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(ShopifyWebhook $message): void
    {
        $delivery = $this->iriConverter->getResourceFromIri($message->getDeliveryIri());

        if (!$delivery instanceof Delivery) {
            return;
        }

        $shopifyOrder = $this->entityManager->getRepository(ShopifyOrder::class)
            ->findOneBy(['delivery' => $delivery]);

        if (!$shopifyOrder) {
            return;
        }

        $shop = $shopifyOrder->getShop();
        $fulfillmentServiceId = $shop->getFulfillmentServiceId();

        if (!$fulfillmentServiceId) {
            return;
        }

        switch ($message->getEvent()) {
            case 'delivery.picked':
                $this->logger->info(sprintf(
                    'Notifying Shopify: order %s picked up.',
                    $shopifyOrder->getShopifyOrderName()
                ));
                $this->shopifyClient->updateFulfillment($shop, $fulfillmentServiceId, 'in_transit');
                break;

            case 'delivery.completed':
                $this->logger->info(sprintf(
                    'Notifying Shopify: order %s delivered.',
                    $shopifyOrder->getShopifyOrderName()
                ));
                $this->shopifyClient->updateFulfillment($shop, $fulfillmentServiceId, 'success');
                break;

            case 'delivery.failed':
                $this->logger->info(sprintf(
                    'Notifying Shopify: order %s delivery failed.',
                    $shopifyOrder->getShopifyOrderName()
                ));
                $this->shopifyClient->updateFulfillment($shop, $fulfillmentServiceId, 'failure');
                break;
        }
    }
}
