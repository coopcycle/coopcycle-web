<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductRepository;
use AppBundle\Integration\Zelty\Dto\ZeltyMenuWebhookPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ZeltyMenuWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $em,
        private readonly ZeltyActivityRecorder $activityRecorder,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        if (!$data instanceof ZeltyMenuWebhookPayload) {
            return new JsonResponse(['status' => 'success']);
        }

        match ($data->eventName) {
            'menu.update'              => $this->handleMenuUpdate($data->data['menus'] ?? []),
            'menu.delete'              => $this->handleMenuDelete($data->data['menus'] ?? []),
            'menu.availability_update' => $this->handleAvailabilityUpdate($data->data),
            default                    => null,
        };

        $this->em->flush();

        return new JsonResponse(['status' => 'success']);
    }

    private function handleMenuUpdate(array $menus): void
    {
        foreach ($menus as $menu) {
            $product = $this->productRepository->findByZeltyItemId((string) $menu['id']);
            if ($product === null) {
                continue;
            }
            $enabled = !($menu['disable'] ?? false);
            $product->setEnabled($enabled);
            $this->recordProductEvent(
                $enabled ? ZeltyActivityRecorder::PRODUCT_ENABLED : ZeltyActivityRecorder::PRODUCT_DISABLED,
                $product
            );
        }
    }

    private function handleMenuDelete(array $menus): void
    {
        foreach ($menus as $menu) {
            $product = $this->productRepository->findByZeltyItemId((string) $menu['id']);
            if ($product === null) {
                continue;
            }
            $product->setEnabled(false);
            $this->recordProductEvent(ZeltyActivityRecorder::PRODUCT_DELETED, $product);
        }
    }

    private function handleAvailabilityUpdate(array $data): void
    {
        if (!isset($data['id_menu'])) {
            return;
        }

        $product = $this->productRepository->findByZeltyItemId((string) $data['id_menu']);
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
