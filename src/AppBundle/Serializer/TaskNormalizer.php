<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Task;
use AppBundle\Service\TagManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class TaskNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $iriConverter;

    public function __construct(
        ItemNormalizer $normalizer,
        IriConverterInterface $iriConverter,
        TagManager $tagManager,
        UserManagerInterface $userManager,
        UploaderHelper $uploaderHelper,
        FilterService $imagineFilter)
    {
        $this->normalizer = $normalizer;
        $this->iriConverter = $iriConverter;
        $this->tagManager = $tagManager;
        $this->userManager = $userManager;
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineFilter = $imagineFilter;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data =  $this->normalizer->normalize($object, $format, $context);

        $data['isAssigned'] = $object->isAssigned();
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

        $data['deliveryColor'] = null;
        if (!is_null($object->getDelivery())) {
            $data['deliveryColor'] = $object->getDelivery()->getColor();
        }

        $data['group'] = null;
        if (null !== $object->getGroup()) {

            $groupTags = [];
            foreach ($object->getGroup()->getTags() as $tag) {
                $groupTags[] = [
                    'name' => $tag->getName(),
                    'slug' => $tag->getSlug(),
                    'color' => $tag->getColor(),
                ];
            }

            $data['group'] = [
                'id' => $object->getGroup()->getId(),
                'name' => $object->getGroup()->getName(),
                'tags' => $groupTags
            ];
        }

        $data['tags'] = [];
        foreach ($object->getTags() as $tag) {
            $data['tags'][] = [
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
                'color' => $tag->getColor(),
            ];
        }

        if (null === $object->getComments()) {
            $data['comments'] = '';
        }

        if (count($object->getImages()) > 0) {
            $data['images'] = [];
            foreach ($object->getImages() as $taskImage) {
                $imagePath = $this->uploaderHelper->asset($taskImage, 'file');
                $data['images'][] = [
                    'id' => $taskImage->getId(),
                    'thumbnail' => $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'task_image_thumbnail')
                ];
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
