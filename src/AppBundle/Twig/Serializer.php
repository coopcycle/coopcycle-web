<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;

use Symfony\Component\Serializer\SerializerInterface;

class Serializer
{
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function normalize($object, $format, $groups = [])
    {
        return $this->serializer->normalize($object, 'json', [
            'groups' => $groups
        ]);
    }
}
