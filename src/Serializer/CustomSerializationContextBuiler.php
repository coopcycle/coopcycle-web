<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class CustomSerializationContextBuiler implements  SerializerContextBuilderInterface
{
    public function __construct(
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly Security $security
    )
    { }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        // Check for a custom serialization group in the URI
        if ($normalization && $request->query->has('groups')) {
            $groups = explode(',', $request->query->get('groups'));
            $context['groups'] = $groups;
        }

        return $context;
    }
}
