<?php

namespace AppBundle\Serializer;

use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Service\TagManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CustomerNormalizer implements NormalizerInterface
{
    private $normalizer;
    private $tagManager;

    public function __construct(NormalizerInterface $normalizer, TagManager $tagManager, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->normalizer = $normalizer;
        $this->tagManager = $tagManager;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['tags']) && is_array($data['tags']) && count($data['tags']) > 0) {
            // Only admins can see customer tags
            if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                $data['tags'] = $this->tagManager->expand($data['tags']);
            } else {
                $data['tags'] = [];
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof CustomerInterface;
    }
}
