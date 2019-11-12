<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Task;
use AppBundle\Service\TagManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $iriConverter;

    public function __construct(
        ItemNormalizer $normalizer,
        IriConverterInterface $iriConverter,
        TagManager $tagManager,
        UserManagerInterface $userManager)
    {
        $this->normalizer = $normalizer;
        $this->iriConverter = $iriConverter;
        $this->tagManager = $tagManager;
        $this->userManager = $userManager;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['doneAfter'])) {
            $data['after'] = $data['doneAfter'];
        }
        if (isset($data['doneBefore'])) {
            $data['before'] = $data['doneBefore'];
        }

        // Make sure "comments" is a string
        if (array_key_exists('comments', $data) && null === $data['comments']) {
            $data['comments'] = '';
        }

        // FIXME Avoid coupling normalizer with groups
        // https://medium.com/@rebolon/the-symfony-serializer-a-great-but-complex-component-fbc09baa65a0
        if (in_array('task', $context['groups'])) {

            $data['assignedTo'] = null;
            if ($object->isAssigned()) {
                $data['assignedTo'] = $object->getAssignedCourier()->getUsername();
            }

            $data['previous'] = null;
            if ($object->hasPrevious()) {
                $data['previous'] = $this->iriConverter->getIriFromItem($object->getPrevious());
            }

            $data['next'] = null;
            if ($object->hasNext()) {
                $data['next'] = $this->iriConverter->getIriFromItem($object->getNext());
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Task;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (!isset($data['doneAfter']) && isset($data['after'])) {
            $data['doneAfter'] = $data['after'];
        }

        if (!isset($data['doneBefore']) && isset($data['before'])) {
            $data['doneBefore'] = $data['before'];
        }

        $task = $this->normalizer->denormalize($data, $class, $format, $context);

        if (isset($data['tags'])) {
            $tags = $this->tagManager->fromSlugs($data['tags']);
            $task->setTags($tags);
        }

        if (isset($data['assignedTo'])) {
            $user = $this->userManager->findUserByUsername($data['assignedTo']);
            if ($user && $user->hasRole('ROLE_COURIER')) {
                $task->assignTo($user);
            }
        }

        return $task;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === Task::class;
    }
}
