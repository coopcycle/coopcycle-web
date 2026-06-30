<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ZeltyCatalogProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ZeltyImportService $importService,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $restaurantId = (int) $this->requestStack->getCurrentRequest()->attributes->get('restaurantId');

        $restaurant = $this->em->getRepository(LocalBusiness::class)->find($restaurantId);
        if ($restaurant === null) {
            return new JsonResponse(['error' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->getConnection()->beginTransaction();
        try {
            $this->importService->import($data, $restaurant);
            $this->em->flush();
            $this->em->getConnection()->commit();
        } catch (\Exception $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }

        return new JsonResponse(['status' => 'success']);
    }
}
