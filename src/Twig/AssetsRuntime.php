<?php

namespace AppBundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

class AssetsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        StorageInterface $storage,
        MountManager $mountManager,
        PropertyMappingFactory $propertyMappingFactory,
        CacheManager $cacheManager,
        Filesystem $assetsFilesystem,
        CacheInterface $appCache)
    {
        $this->storage = $storage;
        $this->mountManager = $mountManager;
        $this->propertyMappingFactory = $propertyMappingFactory;
        $this->cacheManager = $cacheManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->appCache = $appCache;
    }

    public function asset($obj, string $fieldName, string $filter): ?string
    {
        $mapping = $this->propertyMappingFactory->fromField($obj, $fieldName);

        $fileSystem = $this->mountManager->getFilesystem($mapping->getUploadDestination());

        $uri = $this->storage->resolveUri($obj, $fieldName);

        if (!$uri) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($uri, $filter);
    }

    public function assetBase64($obj, string $fieldName, string $filter): ?string
    {
        $mapping = $this->propertyMappingFactory->fromField($obj, $fieldName);

        $fileSystem = $this->mountManager->getFilesystem($mapping->getUploadDestination());

        $uri = $this->storage->resolveUri($obj, $fieldName);

        if (!$uri) {
            return '';
        }

        if (!$fileSystem->has($uri)) {
            return '';
        }

        return (string) ImageManagerStatic::make($fileSystem->read($uri))->encode('data-url');
    }

    public function hasCustomBanner(): bool
    {
        return $this->appCache->get('banner_svg_stat', function (ItemInterface $item) {

            $item->expiresAfter(3600);

            return $this->assetsFilesystem->has('banner.svg');
        });
    }
}
