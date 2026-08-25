<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Sylius\Product;
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
        private readonly ZeltyActivityRecorder $activityRecorder,
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
            $product = $this->productRepository->findByZeltyItemId((string) $dish['id']);
            if ($product === null) {
                continue;
            }
            $enabled = !($dish['disable'] ?? false);
            $product->setEnabled($enabled);
            $this->recordProductEvent(
                $enabled ? ZeltyActivityRecorder::PRODUCT_ENABLED : ZeltyActivityRecorder::PRODUCT_DISABLED,
                $product
            );
        }
    }

    private function handleDishDelete(array $dishes): void
    {
        foreach ($dishes as $dish) {
            $product = $this->productRepository->findByZeltyItemId((string) $dish['id']);
            if ($product === null) {
                continue;
            }
            $product->setEnabled(false);
            $this->recordProductEvent(ZeltyActivityRecorder::PRODUCT_DELETED, $product);
        }
    }

    private function handleAvailabilityUpdate(array $data): void
    {
        if (!isset($data['id_dish'])) {
            return;
        }

        $product = $this->productRepository->findByZeltyItemId((string) $data['id_dish']);
        if ($product === null) {
            return;
        }

        $inStock = !($data['outofstock'] ?? false);
        $product->setEnabled($inStock);
        $this->recordProductEvent(
            $inStock ? ZeltyActivityRecorder::PRODUCT_IN_STOCK : ZeltyActivityRecorder::PRODUCT_OUT_OF_STOCK,
            $product
        );
    }

    private function recordProductEvent(string $type, Product $product): void
    {
        $this->activityRecorder->setRestaurantId($product->getRestaurant()?->getId());
        $this->activityRecorder->record($type, [
            'id'   => $product->getId(),
            'name' => $product->getName(),
        ]);
    }
}
