<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Product\ProductVariantInterface;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductVariantNameGenerator
{

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function generateVariantName(Delivery|Task $object, Delivery $delivery): string
    {
        if ($object instanceof Delivery) {
            return $this->translator->trans('pricing.variant.order_supplement');
        }

        $taskType = $object->getType();

        if ($taskType === Task::TYPE_PICKUP) {
            $translationKey = 'pricing.variant.pickup_point';
        } elseif ($taskType === Task::TYPE_DROPOFF) {
            $translationKey = 'pricing.variant.dropoff_point';
        } else {
            throw new InvalidArgumentException(sprintf('Unknown task type: %s', $taskType));
        }

        $clientName = $object->getAddress()->getName();
        if ($clientName) {
            return sprintf('%s: %s', $this->translator->trans($translationKey), $clientName);
        } else {
            $taskPosition = $this->getTaskPositionByType($delivery, $object);

            return sprintf('%s #%d', $this->translator->trans($translationKey), $taskPosition);
        }
    }

    private function getTaskPositionByType(Delivery $delivery, Task $task): int
    {
        $tasks = $delivery->getTasks();
        $taskType = $task->getType();
        $position = 1;

        foreach ($tasks as $deliveryTask) {
            if ($deliveryTask->getType() === $taskType) {
                if ($deliveryTask === $task) {
                    return $position;
                }
                $position++;
            }
        }

        return $position;
    }

    public function isDeliveryProductVariant(ProductVariantInterface $productVariant): bool
    {
        return str_contains(
            $productVariant->getName(),
            $this->translator->trans('pricing.variant.order_supplement')
        );
    }
}
