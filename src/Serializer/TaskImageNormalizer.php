<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\TaskImage;
use League\Flysystem\FileNotFoundException;
use Imagine\Exception\RuntimeException as ImagineRuntimeException;
use League\Flysystem\FilesystemException;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Exception\LogicException as LiipLogicException;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class TaskImageNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;
    private $uploaderHelper;
    private $imagineFilter;

    public function __construct(
        ItemNormalizer $normalizer,
        UploaderHelper $uploaderHelper,
        FilterService $imagineFilter)
    {
        $this->normalizer = $normalizer;
        $this->uploaderHelper = $uploaderHelper;
        $this->imagineFilter = $imagineFilter;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data =  $this->normalizer->normalize($object, $format, $context);

        try {
            $imagePath = $this->uploaderHelper->asset($object, 'file');
            $data['thumbnail'] = $this->imagineFilter->getUrlOfFilteredImage($imagePath, 'task_image_thumbnail');
        } catch (NotLoadableException | FilesystemException | ImagineRuntimeException | LiipLogicException $e) {
            return $data;
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof TaskImage;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        throw new LogicException();
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }
}
