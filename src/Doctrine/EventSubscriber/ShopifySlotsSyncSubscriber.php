<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Entity\Store;
use AppBundle\Entity\TimeSlot;
use AppBundle\Service\ShopifyClient;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[AsDoctrineListener(event: Events::postUpdate, connection: 'default')]
class ShopifySlotsSyncSubscriber
{
    public function __construct(
        private ShopifyClient $shopifyClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Store) {
            $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
            if (array_key_exists('timeSlot', $changeSet)) {
                $this->syncForStore($entity);
            }
        } elseif ($entity instanceof TimeSlot) {
            $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
            if (array_key_exists('openingHours', $changeSet)) {
                $this->syncForTimeSlot($entity);
            }
        }
    }

    private function syncForTimeSlot(TimeSlot $timeSlot): void
    {
        $stores = $this->entityManager->getRepository(Store::class)
            ->findBy(['timeSlot' => $timeSlot]);

        foreach ($stores as $store) {
            $this->syncForStore($store);
        }
    }

    private function syncForStore(Store $store): void
    {
        $shop = $this->entityManager->getRepository(ShopifyShop::class)
            ->findOneBy(['store' => $store]);

        if (!$shop) {
            return;
        }

        $timeSlot = $store->getTimeSlot();
        if (!$timeSlot) {
            return;
        }

        $spec = $timeSlot->getOpeningHoursSpecification();
        $ok   = $this->shopifyClient->syncSlotsSpec($shop, $spec);

        if (!$ok) {
            $this->logger->error(sprintf(
                'Failed to sync Shopify slots spec for shop %s',
                $shop->getShopDomain()
            ));
        }
    }
}
