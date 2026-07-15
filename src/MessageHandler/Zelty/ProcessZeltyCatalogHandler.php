<?php

namespace AppBundle\MessageHandler\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;
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
            throw $e;
        }

        $this->zeltyCatalogImportsFilesystem->delete($message->s3Key);
    }
}
