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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

        $this->verifySignature($request, $restaurant);

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

    private function verifySignature(\Symfony\Component\HttpFoundation\Request $request, LocalBusiness $restaurant): void
    {
        $secretKey = $restaurant->getZeltyWebhookSecretKey();
        if ($secretKey === null) {
            return;
        }

        $signature = $request->headers->get('x-zelty-hmac-sha256');
        if ($signature === null) {
            throw new AccessDeniedHttpException('Missing webhook signature');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secretKey);
        if (!hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature');
        }
    }
}
