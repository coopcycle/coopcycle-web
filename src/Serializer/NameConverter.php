<?php

namespace AppBundle\Serializer;

use AppBundle\Entity\Sylius\Taxon;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class NameConverter implements AdvancedNameConverterInterface
{
    public function __construct(private readonly AdvancedNameConverterInterface $nameConverter)
    {
    }

    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (Taxon::class === $class && 'menuChildren' === $propertyName) {
            return isset($context['object']) ? ($context['object']->isRoot() ? 'hasMenuSection' : 'hasMenuItem') : 'hasMenuSection';
        }

        return $this->nameConverter->normalize($propertyName, $class, $format, $context);
    }

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        return $this->nameConverter->denormalize($propertyName, $class, $format, $context);
    }
}
