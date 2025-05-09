<?php

namespace AppBundle\Domain\Tour;

use AppBundle\Domain\Event as BaseEvent;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Entity\Tour;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

abstract class Event extends BaseEvent implements SerializableEventInterface
{

    public function __construct(protected Tour $tour)
    {}

    public function normalize(NormalizerInterface $serializer)
    {
        $normalized = $serializer->normalize(
            $this->tour,
            'jsonld', [
                'resource_class' => Tour::class,
                'groups' => ['task_collection', 'tour']
            ]);

        return [
            'tour' => $normalized
        ];
    }
}
