<?php

namespace AppBundle\Integration\Zelty;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Integration\Zelty\Dto\ZeltyOptionWebhookPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ZeltyOptionWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        if (!$data instanceof ZeltyOptionWebhookPayload) {
            return new JsonResponse(['status' => 'success']);
        }

        match ($data->eventName) {
            'option.update'                    => $this->handleOptionUpdate($data->restaurantId, $data->data['options'] ?? []),
            'option_value.availability_update' => $this->handleAvailabilityUpdate($data->data['options_values_availabilities'] ?? []),
            default                            => null,
        };

        $this->em->flush();

        return new JsonResponse(['status' => 'success']);
    }

    private function handleOptionUpdate(int $restaurantId, array $options): void
    {
        foreach ($options as $option) {
            $enabled = !($option['disable'] ?? false);
            foreach ($option['values'] ?? [] as $valueData) {
                $code = sprintf('%d_%d', $valueData['id'], $restaurantId);
                $value = $this->em->getRepository(ProductOptionValue::class)->findOneBy(['code' => $code]);
                if ($value === null) {
                    continue;
                }
                $value->setEnabled($enabled);
                $value->setPrice((int) $valueData['price']);
            }
        }
    }

    private function handleAvailabilityUpdate(array $items): void
    {
        foreach ($items as $item) {
            $code = sprintf('%d_%d', $item['id_dish_option_value'], $item['id_restaurant']);
            $value = $this->em->getRepository(ProductOptionValue::class)->findOneBy(['code' => $code]);
            if ($value === null) {
                continue;
            }
            $value->setEnabled(!$item['outofstock']);
        }
    }
}
