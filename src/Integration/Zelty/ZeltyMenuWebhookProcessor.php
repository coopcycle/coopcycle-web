<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
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
            $product->setEnabled(!($menu['disable'] ?? false));
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

        $product->setEnabled(!($data['outofstock'] ?? false));
    }
}
