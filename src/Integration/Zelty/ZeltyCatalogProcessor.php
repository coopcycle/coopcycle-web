<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Message\Zelty\ProcessZeltyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class ZeltyCatalogProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly MessageBusInterface $messageBus,
        private readonly Filesystem $zeltyCatalogImportsFilesystem,
        private readonly ZeltyActivityRecorder $activityRecorder,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $restaurantId = (int) $uriVariables['restaurantId'];

        $restaurant = $this->em->getRepository(LocalBusiness::class)->find($restaurantId);
        if ($restaurant === null) {
            return new JsonResponse(['error' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
        }

        $s3Key = sprintf('catalog_%d_%s.json', $restaurantId, uniqid('', true));
        $this->zeltyCatalogImportsFilesystem->write($s3Key, $request->getContent());

        $this->messageBus->dispatch(new ProcessZeltyCatalog($restaurantId, $s3Key));

        $this->activityRecorder->setRestaurantId($restaurantId);
        $this->activityRecorder->record(ZeltyActivityRecorder::CATALOG_RECEIVED);

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
