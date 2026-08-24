<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Integration\Zelty\Dto\ZeltyCatalog;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ZeltyCatalogDenormalizer implements DenormalizerInterface
{
    public function __construct(private readonly ZeltyCatalogParser $parser) {}

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ZeltyCatalog
    {
        return $this->parser->parse($data);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ZeltyCatalog::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ZeltyCatalog::class => true];
    }
}
