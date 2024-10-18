<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Api\Dto\MyTaskDto;
use AppBundle\Entity\Task;
use AppBundle\Service\TagManager;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class MyTaskDtoNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'MyTaskDtoNormalizer_ALREADY_CALLED';

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly TagManager $tagManager,
    )
    {
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to match the existing API
        $data['@context'] = '/api/contexts/Task';
        $data['@type'] = 'Task';
        $data['@id'] = "/api/tasks/" . $object->id;

        // Make sure "comments" is a string
        if (array_key_exists('comments', $data) && null === $data['comments']) {
            $data['comments'] = '';
        }

        if (isset($data['tags']) && count($data['tags']) > 0) {
            $data['tags'] = $this->tagManager->expand($data['tags']);
        }

        if ($object->previous) {
            $data['previous'] = $this->iriConverter->getItemIriFromResourceClass(Task::class, ['id' => $object->previous]);
        }

        if ($object->next) {
            $data['next'] = $this->iriConverter->getItemIriFromResourceClass(Task::class, ['id' => $object->next]);
        }

        // Make sure "orgName" is a string
        if (array_key_exists('orgName', $data) && null === $data['orgName']) {
            $data['orgName'] = '';
        }

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof MyTaskDto;
    }
}
