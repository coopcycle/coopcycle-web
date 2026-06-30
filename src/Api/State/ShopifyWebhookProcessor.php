<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Resource\ShopifyWebhook;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Shopify\ShopifyOrder;
use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Entity\Task;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TaskManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

class ShopifyWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeliveryManager $deliveryManager,
        private TaskManager $taskManager,
        private Geocoder $geocoder,
        private PhoneNumberUtil $phoneNumberUtil,
        private LoggerInterface $logger,
        private string $country,
    ) {}

    /**
     * @param ShopifyWebhook $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShopifyWebhook
    {
        $shop = $this->entityManager->getRepository(ShopifyShop::class)->find($data->id);

        if (!$shop || !$shop->getStore()) {
            $this->logger->warning(sprintf(
                'Shopify webhook received for shop id %d but no store is linked.',
                $data->id
            ));

            return $data;
        }

        switch ($data->topic) {
            case ShopifyWebhook::EVENT_ORDER_CREATED:
                $this->onOrderCreated($data->payload, $shop);
                break;
            case ShopifyWebhook::EVENT_ORDER_CANCELLED:
                $this->onOrderCancelled($data->payload);
                break;
            default:
                $this->logger->info(sprintf('Unhandled Shopify topic "%s"', $data->topic));
        }

        return $data;
    }

    private function onOrderCreated(array $order, ShopifyShop $shop): void
    {
        $shopifyOrderId = (string) $order['id'];

        $existing = $this->entityManager->getRepository(ShopifyOrder::class)
            ->findOneBy(['shopifyOrderId' => $shopifyOrderId]);

        if ($existing) {
            $this->logger->info(sprintf(
                'Shopify order %s already processed, skipping.',
                $shopifyOrderId
            ));
            return;
        }

        $delivery = $this->buildDelivery($order, $shop);

        if (!$delivery) {
            return;
        }

        $shop->getStore()->addDelivery($delivery);
        $this->deliveryManager->setDefaults($delivery);

        $this->entityManager->persist($delivery);

        $shopifyOrder = new ShopifyOrder();
        $shopifyOrder->setShopifyOrderId($shopifyOrderId);
        $shopifyOrder->setShopifyOrderName($order['name'] ?? $shopifyOrderId);
        $shopifyOrder->setDelivery($delivery);
        $shopifyOrder->setShop($shop);

        $this->entityManager->persist($shopifyOrder);
        $this->entityManager->flush();

        $this->logger->info(sprintf(
            'Created delivery for Shopify order %s (%s)',
            $shopifyOrderId,
            $order['name'] ?? ''
        ));
    }

    private function onOrderCancelled(array $order): void
    {
        $shopifyOrderId = (string) $order['id'];

        $shopifyOrder = $this->entityManager->getRepository(ShopifyOrder::class)
            ->findOneBy(['shopifyOrderId' => $shopifyOrderId]);

        if (!$shopifyOrder || !$shopifyOrder->getDelivery()) {
            $this->logger->info(sprintf(
                'No delivery found for cancelled Shopify order %s.',
                $shopifyOrderId
            ));
            return;
        }

        $delivery = $shopifyOrder->getDelivery();

        foreach ($delivery->getTasks() as $task) {
            if (!$task->isCancelled()) {
                $this->taskManager->cancel($task);
            }
        }

        $this->entityManager->flush();

        $this->logger->info(sprintf('Cancelled delivery for Shopify order %s', $shopifyOrderId));
    }

    private function buildDelivery(array $order, ShopifyShop $shop): ?Delivery
    {
        $shippingAddress = $order['shipping_address'] ?? null;
        if (!$shippingAddress) {
            $this->logger->warning(sprintf(
                'Shopify order %s has no shipping address.',
                $order['id']
            ));
            return null;
        }

        $dropoffAddress = $this->buildAddress($shippingAddress);
        if (!$dropoffAddress) {
            $this->logger->error(sprintf(
                'Could not geocode shipping address for Shopify order %s.',
                $order['id']
            ));
            return null;
        }

        $delivery = new Delivery();

        $dropoff = $delivery->getDropoff();
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setRef((string) $order['id']);

        $orderNote = $order['note'] ?? '';
        if (!empty($orderNote)) {
            $dropoff->setComments($orderNote);
        }

        // Requested delivery time window from order note attributes (optional)
        $this->applyTimeWindow($dropoff, $order);

        // Fall back to today's window if no date was provided so the task is always schedulable.
        if ($dropoff->getBefore() === null) {
            $dropoff->setAfter(Carbon::now()->startOfDay()->toDateTime());
            $dropoff->setBefore(Carbon::now()->endOfDay()->toDateTime());
        }

        return $delivery;
    }

    private function buildAddress(array $addressData): ?Address
    {
        $street = trim(implode(' ', array_filter([
            $addressData['address1'] ?? '',
            $addressData['address2'] ?? '',
        ])));

        $streetAddress = sprintf('%s, %s %s, %s',
            $street,
            $addressData['zip'] ?? '',
            $addressData['city'] ?? '',
            $addressData['country'] ?? ''
        );

        if (isset($addressData['latitude'], $addressData['longitude'])
            && $addressData['latitude'] && $addressData['longitude']) {
            $address = new Address();
            $address->setStreetAddress($streetAddress);
            $address->setGeo(new GeoCoordinates(
                (float) $addressData['latitude'],
                (float) $addressData['longitude']
            ));
        } else {
            $address = $this->geocoder->geocode($streetAddress);
            if (!$address) {
                return null;
            }
        }

        $contactName = trim(($addressData['first_name'] ?? '') . ' ' . ($addressData['last_name'] ?? ''));
        if ($contactName) {
            $address->setContactName($contactName);
        }

        $phone = $addressData['phone'] ?? null;
        if ($phone) {
            try {
                $address->setTelephone(
                    $this->phoneNumberUtil->parse($phone, strtoupper($this->country))
                );
            } catch (NumberParseException) {}
        }

        return $address;
    }

    private function applyTimeWindow(Task $task, array $order): void
    {
        $noteAttributes = $order['note_attributes'] ?? [];
        $deliveryDate   = null;
        $deliveryTime   = null;

        // Shopify's native local delivery date picker stores date/time in note_attributes.
        // The attribute names depend on what the merchant configured in their Shopify admin
        // (typically "Delivery Date" and "Delivery Time").
        $dateKeys = ['delivery date', 'requested_delivery_date', 'delivery_date'];
        $timeKeys = ['delivery time', 'delivery slot', 'delivery_time'];

        foreach ($noteAttributes as $attr) {
            $name = strtolower(trim($attr['name'] ?? ''));
            if (in_array($name, $dateKeys, true)) {
                $deliveryDate = $attr['value'] ?? null;
            } elseif (in_array($name, $timeKeys, true)) {
                $deliveryTime = $attr['value'] ?? null;
            }
        }

        if (!$deliveryDate) {
            return;
        }

        try {
            $after  = Carbon::parse($deliveryDate)->startOfDay();
            $before = Carbon::parse($deliveryDate)->endOfDay();

            // "14:00 - 16:00" or "14:00-16:00" — narrow the window to the slot.
            if ($deliveryTime && preg_match('/(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/', $deliveryTime, $m)) {
                $after  = Carbon::parse($deliveryDate . ' ' . $m[1]);
                $before = Carbon::parse($deliveryDate . ' ' . $m[2]);
            }

            $task->setAfter($after->toDateTime());
            $task->setBefore($before->toDateTime());
        } catch (\Exception) {}
    }
}
