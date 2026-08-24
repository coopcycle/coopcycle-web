<?php

namespace AppBundle\Serializer;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use AppBundle\Entity\Incident\Incident;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class IncidentNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ItemNormalizer $normalizer,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    )
    {}

    public function normalize($object, $format = null, array $context = array())
    {
        // Since API Platform 2.7, IRIs for custom operations have changed
        // It means that when doing GET /api/incidents, the @id will be /api/incidents, not /api/incidents/{id} like before
        // In our JS code, we often override the state with the entire response
        // This custom code makes sure it works like before, by tricking IriConverter
        $context['operation'] = $this->resourceMetadataFactory->create(Incident::class)->getOperation();
        // The 'operation' key is excluded from the serializer cache key because serializing an API Platform
        // Operation object pulls in a large object graph and causes out-of-memory errors.
        // Same as in TaskNormalizer.
        $context[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY][] = 'operation';

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data)) {
            return $data;
        }

        // Add custom nested fields
        $createdBy = $object->getCreatedBy();
        $data['author'] = [
            'id' => $createdBy?->getId(),
            'username' => $createdBy?->getUsername()
        ];

        // When the "task" group is not requested, the task is normalized as an IRI,
        // and the incidents list needs those fields to be readable without an extra request.
        // When it *is* requested (incident details page), we must not throw away
        // the normalized task, which contains the address, the packages, etc.
        $task = $object->getTask();
        $data['task'] = array_merge(
            is_array($data['task'] ?? null) ? $data['task'] : [],
            [
                'id' => $task->getId(),
                'status' => $task->getStatus(),
                'type' => $task->getType()
            ]
        );

        $delivery = $task?->getDelivery();
        $data['delivery'] = [
            'id' => $delivery?->getId()
        ];

        $order = $delivery?->getOrder();
        $restaurant = null;
        if ($order && $order->getVendors() && $order->getVendors()->count() > 0) {
            $restaurant = $order->getVendors()->first()->getRestaurant();
        }
        $data['order'] = [
            'id' => $order?->getId(),
            'restaurant' => [
                'id' => $restaurant?->getId(),
                'name' => $restaurant?->getName()
            ],
            'customer' => [
                'id' => $order?->getCustomer()?->getUser()?->getId(),
                'username' => $order?->getCustomer()?->getUser()?->getUsername()
            ]
        ];

        $store = $delivery?->getStore();
        $data['store'] = [
            'id' => $store?->getId(),
            'name' => $store?->getName()
        ];

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Incident;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Incident::class => false, // supports*() call result is NOT cached
        ];
    }
}
