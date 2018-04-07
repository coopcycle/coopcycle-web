<?php

namespace AppBundle\Serializer\Json;

use AppBundle\Entity\Notification;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Translation\TranslatorInterface;

class NotificationNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $urlGenerator;
    private $translator;

    public function __construct(
        ObjectNormalizer $normalizer,
        UrlGeneratorInterface $urlGenerator,
        TranslatorInterface $translator)
    {
        $this->normalizer = $normalizer;
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        unset($data['routeName']);
        unset($data['routeParameters']);
        unset($data['user']);

        $data['message'] = $this->translator->trans($data['message']);

        if (null !== $object->getRouteName()) {
            $data['url'] = $this->urlGenerator->generate($object->getRouteName(), $object->getRouteParameters());
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Notification;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return null;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }
}
