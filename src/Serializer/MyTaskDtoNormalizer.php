<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Api\Dto\MyTaskDto;
use AppBundle\Service\TagManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MyTaskDtoNormalizer implements NormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer,
        private IriConverterInterface $iriConverter,
        private TagManager $tagManager,
    )
    {}

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data)) {

            return $data;
        }

        // Make sure "comments" is a string
        if (array_key_exists('comments', $data) && null === $data['comments']) {
            $data['comments'] = '';
        }

        if (isset($data['tags']) && is_array($data['tags']) && count($data['tags']) > 0) {
            $data['tags'] = $this->tagManager->expand($data['tags']);
        }

        $data['previous'] = null;
        if ($object->hasPrevious()) {
            $data['previous'] = $this->iriConverter->getIriFromItem($object->getPrevious());
        }

        $data['next'] = null;
        if ($object->hasNext()) {
            $data['next'] = $this->iriConverter->getIriFromItem($object->getNext());
        }

        //TODO; fix me
//        if (array_key_exists('metadata', $data) && is_array($data['metadata'])) {
//            if ($order = $object->getDelivery()?->getOrder()) {
//                $data['metadata'] = array_merge($data['metadata'], ['zero_waste' => $order->isZeroWaste()]);
//                if ($object->isDropoff()) {
//                    $data['metadata'] = array_merge($data['metadata'], ['has_loopeat_returns' => $order->hasLoopeatReturns()]);
//                }
//            }
//        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof MyTaskDto;
    }
}
