<?php

namespace AppBundle\MessageHandler\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Zelty\ApiLog;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;
use AppBundle\Integration\Zelty\ZeltyActivityRecorder;
use AppBundle\Integration\Zelty\ZeltyImportService;
use AppBundle\Message\Zelty\ProcessZeltyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessZeltyCatalogHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ZeltyImportService $importService,
        private readonly ZeltyCatalogParser $parser,
        private readonly Filesystem $zeltyCatalogImportsFilesystem,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?LoggerInterface $zeltyLogger = null,
    ) {}

    public function __invoke(ProcessZeltyCatalog $message): void
    {
        $restaurant = $this->em->getRepository(LocalBusiness::class)->find($message->restaurantId);
        if ($restaurant === null) {
            $this->logger?->error('Zelty catalog import: restaurant {id} not found', ['id' => $message->restaurantId]);
            return;
        }

        $json = $this->zeltyCatalogImportsFilesystem->read($message->s3Key);
        $payload = json_decode($json, true);
        $catalog = $this->parser->parse($payload);

        $this->em->getConnection()->beginTransaction();
        try {
            $this->importService->import($catalog, $restaurant);
            $this->em->flush();
            $this->em->getConnection()->commit();
        } catch (\Exception $e) {
            $this->em->getConnection()->rollBack();
            // The webhook only ever said "queued": a failure here is invisible
            // otherwise, so it belongs in the shop's activity.
            $this->logActivity($message->restaurantId, 'error', [[
                'type'   => ZeltyActivityRecorder::CATALOG_IMPORT_FAILED,
                'params' => ['error' => $e->getMessage()],
            ]], $e->getMessage());

            throw $e;
        }

        $this->logActivity($message->restaurantId, 'info', [[
            'type'   => ZeltyActivityRecorder::CATALOG_IMPORTED,
            'params' => [
                'name'   => $catalog->name,
                'dishes' => count($catalog->getDishes()),
                'menus'  => count($catalog->getMenus()),
            ],
        ]]);

        $this->zeltyCatalogImportsFilesystem->delete($message->s3Key);
    }

    /**
     * The import runs in a worker, long after the webhook was answered: it gets
     * its own line in the API log so the outcome is visible.
     */
    private function logActivity(int $restaurantId, string $level, array $events, ?string $error = null): void
    {
        $this->zeltyLogger?->log($level, 'Zelty catalog import', [
            'direction'     => ApiLog::DIRECTION_INCOMING,
            'restaurant_id' => $restaurantId,
            // Not an HTTP call: this row is the outcome of the background job
            'method'        => 'JOB',
            'endpoint'      => 'ProcessZeltyCatalog',
            'status_code'   => $error === null ? 200 : null,
            'error'         => $error,
            'events'        => $events,
        ]);
    }
}
