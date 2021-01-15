<?php

namespace AppBundle\Api\Swagger;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SwaggerDecorator implements NormalizerInterface
{
    private $decorated;

    public function __construct(NormalizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $docs = $this->decorated->normalize($object, $format, $context);

        unset($docs['paths']['/api/api_apps/{id}']);
        unset($docs['paths']['/api/opening_hours_specifications/{id}']);
        unset($docs['paths']['/api/pricing/calculate-price']);
        unset($docs['paths']['/api/task_events/{id}']);

        return $docs;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
