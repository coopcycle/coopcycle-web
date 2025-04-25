<?php

namespace AppBundle\Serializer;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use AppBundle\Entity\Task;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class CustomSerializationContextBuiler implements SerializerContextBuilderInterface
{
    public function __construct(
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory
    ) {}

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        // Check for a custom serialization group in the URI
        if ($normalization && $request->query->has('groups')) {
            $groups = explode(',', $request->query->get('groups'));
            $context['groups'] = $groups;
        }

        if ($normalization) {
            // Since API Platform 2.7, IRIs for custom operations have changed
            // It means that when doing PUT /api/tasks/{id}/assign, the @id will be /api/tasks/{id}/assign, not /api/tasks/{id} like before
            // In our JS code, we often override the state with the entire response
            // This custom method makes sure it works like before.
            $context = $this->overrideItemUriTemplate($context);
        }

        return $context;
    }

    /**
     * Adds "item_uri_template" to context if needed.
     */
    private function overrideItemUriTemplate($context)
    {
        $classes = [
            Task::class,
        ];
        $operation = $context['operation'] ?? null;
        if (is_object($operation) && $operation instanceof Put && in_array($context['resource_class'], $classes)) {
            $getOperation = $this->resourceMetadataFactory->create($context['resource_class'])->getOperation();
            $context['item_uri_template'] = $getOperation->getName();
        }

        return $context;
    }
}
