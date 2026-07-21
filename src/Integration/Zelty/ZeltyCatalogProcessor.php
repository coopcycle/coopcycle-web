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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class ZeltyCatalogProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly MessageBusInterface $messageBus,
        private readonly Filesystem $zeltyCatalogImportsFilesystem,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $restaurantId = (int) $uriVariables['restaurantId'];

        $restaurant = $this->em->getRepository(LocalBusiness::class)->find($restaurantId);
        if ($restaurant === null) {
            return new JsonResponse(['error' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
        }

        $this->verifySignature($request, $restaurant);

        $s3Key = sprintf('catalog_%d_%s.json', $restaurantId, uniqid('', true));
        $this->zeltyCatalogImportsFilesystem->write($s3Key, $request->getContent());

        $this->messageBus->dispatch(new ProcessZeltyCatalog($restaurantId, $s3Key));

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    private function verifySignature(\Symfony\Component\HttpFoundation\Request $request, LocalBusiness $restaurant): void
    {
        $secretKey = $restaurant->getZeltyWebhookSecretKey();
        if ($secretKey === null || str_contains($secretKey, '*')) {
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
