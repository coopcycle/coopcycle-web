<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc;

use AppBundle\Entity\Store;
use AppBundle\Entity\StoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RdcStoreResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolveStore(?string $rdcConnectionId = null): ?Store
    {
        /** @var StoreRepository $repo */
        $repo = $this->entityManager->getRepository(Store::class);

        // 1. If RDC connection ID provided, find by that ID
        if ($rdcConnectionId !== null) {
            $store = $repo->findOneByRdcConnectionId($rdcConnectionId);
            if ($store !== null) {
                $this->logger->info('Resolved store by RDC connection ID', [
                    'rdc_connection_id' => $rdcConnectionId,
                    'store_id' => $store->getId(),
                ]);
                return $store;
            }
        }

        // 2. If exactly one store has RDC connection, use that
        $storesWithRdc = $repo->findStoresWithRdcConnection();
        if (count($storesWithRdc) === 1) {
            $this->logger->info('Resolved single store with RDC connection', [
                'store_id' => $storesWithRdc[0]->getId(),
            ]);
            return $storesWithRdc[0];
        }

        // 3. If exactly one store exists total, use that
        $store = $repo->findSingleStore();
        if ($store !== null) {
            $this->logger->info('Resolved single store as fallback', [
                'store_id' => $store->getId(),
            ]);
            return $store;
        }

        $this->logger->error('Cannot resolve store for RDC');
        return null;
    }
}
