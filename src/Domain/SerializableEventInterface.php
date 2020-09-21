<?php

namespace AppBundle\Domain;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

interface SerializableEventInterface
{
    public function normalize(NormalizerInterface $serializer);
}
