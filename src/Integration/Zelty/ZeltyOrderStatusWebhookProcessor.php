<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Integration\Zelty\Dto\ZeltyOrderStatusWebhookPayload;
use AppBundle\Service\OrderManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ZeltyOrderStatusWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderManager $orderManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        if (!$data instanceof ZeltyOrderStatusWebhookPayload) {
            return new JsonResponse(['status' => 'success']);
        }

        $order = $this->orderRepository->findByZeltyOrderId($data->zeltyOrderId);

        if ($order !== null) {
            match ($data->status) {
                'production' => $this->orderManager->startPreparing($order),
                'ready'      => $this->orderManager->finishPreparing($order),
                default      => null,
            };
        }

        return new JsonResponse(['status' => 'success']);
    }
}
