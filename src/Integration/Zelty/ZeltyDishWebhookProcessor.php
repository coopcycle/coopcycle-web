<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Sylius\ProductRepository;
use AppBundle\Integration\Zelty\Dto\ZeltyDishWebhookPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ZeltyDishWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        if (!$data instanceof ZeltyDishWebhookPayload) {
            return new JsonResponse(['status' => 'success']);
        }

        match ($data->eventName) {
            'dish.update'              => $this->handleDishUpdate($data->data['dishes'] ?? []),
            'dish.delete'              => $this->handleDishDelete($data->data['dishes'] ?? []),
            'dish.availability_update' => $this->handleAvailabilityUpdate($data->data),
            default                    => null,
        };

        $this->em->flush();

        return new JsonResponse(['status' => 'success']);
    }

    private function handleDishUpdate(array $dishes): void
    {
        foreach ($dishes as $dish) {
            $product = $this->productRepository->findByZeltyDishId((string) $dish['id']);
            if ($product === null) {
                continue;
            }
            $product->setEnabled(!($dish['disable'] ?? false));
        }
    }

    private function handleDishDelete(array $dishes): void
    {
        foreach ($dishes as $dish) {
            $product = $this->productRepository->findByZeltyDishId((string) $dish['id']);
            if ($product === null) {
                continue;
            }
            $product->setEnabled(false);
        }
    }

    private function handleAvailabilityUpdate(array $data): void
    {
        if (!isset($data['id_dish'])) {
            return;
        }

        $product = $this->productRepository->findByZeltyDishId((string) $data['id_dish']);
        if ($product === null) {
            return;
        }

        $product->setEnabled(!($data['outofstock'] ?? false));
    }
}
