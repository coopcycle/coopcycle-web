<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Task;
use AppBundle\Service\TagManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DeliveryMapper
{

    public function __construct(
        private readonly TaskMapper $taskMapper,
        private readonly TagManager $tagManager,
        private readonly NormalizerInterface $normalizer,
        private readonly ObjectNormalizer $symfonyNormalizer,
    ) {
    }

    public function map(
        Delivery $deliveryEntity,
        ?OrderInterface $order,
        ?ArbitraryPrice $arbitraryPrice,
        bool $isSavedOrder,
        array $groups = []
    ): DeliveryInputDto {

        $context = !empty($groups) ? ['groups' => $groups] : [];

        $deliveryData = new DeliveryInputDto();

        $deliveryData->tasks = array_map(function (Task $taskEntity) use ($context) {

            $taskData = new TaskDto();

            $taskData->id = $taskEntity->getId();
            $taskData->createdAt = $taskEntity->getCreatedAt();
            $taskData->updatedAt = $taskEntity->getUpdatedAt();

            $taskData->type = $taskEntity->getType();
            $taskData->status = $taskEntity->getStatus();

            $address = $taskEntity->getAddress();
            if ($address->getId() !== null) {
                $taskData->address = $this->normalizer->normalize($address, 'jsonld', $context);
                // Workaround to properly normalize embedded relation
                // Should become unnecessary when we normalise address using built-in normalizer
                // See a comment at TaskDto Address property
                if (isset($taskData->address['@context'])) {
                    unset($taskData->address['@context']);
                }
            } else {
                // a case when address doesn't have an ID (for example, in Recurrence rules)
                // (we can't use API platform normalizer here, as it fails with: Unable to generate an IRI for the item)
                $taskData->address = $this->symfonyNormalizer->normalize($address, 'json', $context);
            }

            $taskData->after = $taskEntity->getAfter();
            $taskData->before = $taskEntity->getBefore();
            $taskData->doneAfter = $taskEntity->getAfter();
            $taskData->doneBefore = $taskEntity->getBefore();

            $taskData->comments = $taskEntity->getComments();
            $taskData->tags = $this->tagManager->expand($taskEntity->getTags());
            //as this mapper is currently only used to prefill the form-data,
            // we want to return the original data without the sum of all the weights
            // otherwise the weight will be multiplied by each edit
            $taskData->weight = $this->taskMapper->getWeight($taskEntity, []);
            //as this mapper is currently only used to prefill the form-data,
            // we want to return the original data without the sum of all the packages
            // otherwise the packages will be multiplied by each edit
            $taskData->packages = $this->taskMapper->getPackages($taskEntity, []);
            $taskData->metadata = $taskEntity->getMetadata();

            if (!is_null($taskEntity->getId())) {
                $taskData->barcode = $this->taskMapper->getBarcode($taskEntity);
            }

            return $taskData;
        }, $deliveryEntity->getTasks());

        $deliveryData->pickup = $deliveryData->tasks[0] ?? null;
        $deliveryData->dropoff = end($deliveryData->tasks) ?: null;

        $deliveryOrderData = new DeliveryOrderDto();
        $deliveryData->order = $deliveryOrderData;

        if (!is_null($order)) {
            if (!is_null($order->getId())) {
                $deliveryOrderData->id = $order->getId();
            }

            $deliveryOrderData->manualSupplements = $this->extractManualSupplementsFromOrder(
                $order
            );
        }

        $deliveryOrderData->arbitraryPrice = $arbitraryPrice ? new ArbitraryPriceDto(
            $arbitraryPrice->getValue(),
            $arbitraryPrice->getVariantName()
        ) : null;

        $deliveryOrderData->isSavedOrder = $isSavedOrder;

        if ($deliveryEntity->getId()) {
            $deliveryData->id = $deliveryEntity->getId();
        }

        return $deliveryData;
    }

    /**
     * @param OrderInterface $order
     * @return ManualSupplementDto[]
     */
    private function extractManualSupplementsFromOrder(OrderInterface $order): array
    {
        $manualSupplements = [];

        foreach ($order->getItems() as $orderItem) {
            /** @var OrderItemInterface $orderItem */
            $variant = $orderItem->getVariant();

            if (null === $variant) {
                continue;
            }

            foreach ($variant->getOptionValues() as $productOptionValue) {
                /** @var ProductOptionValue $productOptionValue */

                try {
                    // Find the PricingRule linked to this ProductOptionValue
                    $pricingRule = $productOptionValue->getPricingRule();
                } catch (EntityNotFoundException $e) {
                    // This happens when a pricing rule has been modified
                    // and the linked product option value has been disabled
                    // but is still attached to a product variant
                    // Don't return this value to a user, so they can keep an existing supplement, but can't edit it
                    continue;
                }

                if (null !== $pricingRule && $pricingRule->isManualSupplement()) {
                    // Create ManualSupplementDto
                    $manualSupplementDto = new ManualSupplementDto();
                    $manualSupplementDto->pricingRule = $pricingRule;
                    $manualSupplementDto->quantity = $variant->formatQuantityForOptionValue($productOptionValue);

                    $manualSupplements[] = $manualSupplementDto;
                }
            }
        }

        return $manualSupplements;
    }
}
