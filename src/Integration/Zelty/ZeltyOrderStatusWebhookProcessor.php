<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Integration\Zelty\Dto\ZeltyOrderStatusWebhookPayload;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ZeltyOrderStatusWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderManager $orderManager,
        private readonly ZeltyActivityRecorder $activityRecorder,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        if (!$data instanceof ZeltyOrderStatusWebhookPayload) {
            return new JsonResponse(['status' => 'success']);
        }

        $order = $this->orderRepository->findByZeltyOrderId($data->zeltyOrderId);

        // Zelty broadcasts the status of every order rung up on the till, most of
        // which never came from us; those are not ours to react to, nor to log.
        if ($order !== null) {
            switch ($data->status) {
                case 'production':
                    $this->orderManager->startPreparing($order);
                    $this->recordEvent(ZeltyActivityRecorder::ORDER_PREPARING, $order);
                    break;
                case 'ready':
                    $this->orderManager->finishPreparing($order);
                    $this->recordEvent(ZeltyActivityRecorder::ORDER_READY, $order);
                    break;
            }
        }

        return new JsonResponse(['status' => 'success']);
    }

    private function recordEvent(string $type, OrderInterface $order): void
    {
        $this->activityRecorder->setRestaurantId($order->getRestaurant()?->getId());
        $this->activityRecorder->record($type, [
            'id'     => $order->getId(),
            'number' => $order->getNumber(),
        ]);
    }
}
