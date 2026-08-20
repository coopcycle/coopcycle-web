<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Message\Zelty\ProcessZeltyCatalog;
use League\Flysystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fetches a catalog from the Zelty API instead of waiting for the restaurant
 * to push it, and hands it over to the same background import job.
 */
class ZeltyCatalogPullService
{
    public function __construct(
        private readonly ZeltyClient $zeltyClient,
        private readonly MessageBusInterface $messageBus,
        private readonly Filesystem $zeltyCatalogImportsFilesystem,
    ) {}

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     */
    public function listCatalogs(LocalBusiness $restaurant): array
    {
        $this->zeltyClient->setAuth($restaurant->getZeltyApiKey());

        return $this->zeltyClient->getCatalogs();
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     */
    public function pull(LocalBusiness $restaurant, string $catalogId): void
    {
        $this->zeltyClient->setAuth($restaurant->getZeltyApiKey());

        $catalog = $this->zeltyClient->getCatalog($catalogId);

        // The import job expects the same envelope as the catalog.push webhook.
        $s3Key = sprintf('catalog_%d_%s.json', $restaurant->getId(), uniqid('', true));
        $this->zeltyCatalogImportsFilesystem->write($s3Key, json_encode(['data' => $catalog]));

        $this->messageBus->dispatch(new ProcessZeltyCatalog($restaurant->getId(), $s3Key));
    }
}
