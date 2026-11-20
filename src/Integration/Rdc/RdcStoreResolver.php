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

    /**
     * The store linked to the connection the service request came from. Each
     * connection has its own store: there's no fallback, which would put a
     * requestor's deliveries in another requestor's store.
     */
    public function resolveStore(string $rdcConnectionId): ?Store
    {
        /** @var StoreRepository $repo */
        $repo = $this->entityManager->getRepository(Store::class);

        $store = $repo->findOneByRdcConnectionId($rdcConnectionId);
        if (is_null($store)) {
            $this->logger->error('No store is linked to the RDC connection', [
                'rdc_connection_id' => $rdcConnectionId,
            ]);

            return null;
        }

        $this->logger->info('Resolved store by RDC connection ID', [
            'rdc_connection_id' => $rdcConnectionId,
            'store_id' => $store->getId(),
        ]);

        return $store;
    }
}
