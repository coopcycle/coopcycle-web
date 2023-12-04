<?php

namespace AppBundle\Twig;

use Aws\S3\Exception\S3Exception;
use Twig\Extension\RuntimeExtensionInterface;
use Hashids\Hashids;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        UrlGeneratorInterface $urlGenerator,
        CacheInterface $projectCache,
        FilterManager $filterManager,
        Hashids $hashids12,
        string $pixabayApiKey)
    {
        $this->storage = $storage;
        $this->mountManager = $mountManager;
        $this->propertyMappingFactory = $propertyMappingFactory;
        $this->cacheManager = $cacheManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->urlGenerator = $urlGenerator;
        $this->projectCache = $projectCache;
        $this->filterManager = $filterManager;
        $this->hashids12 = $hashids12;
        $this->isPixabayConfigured = !empty($pixabayApiKey);
    }

    public function asset($obj, string $fieldName, string $filter, bool $generateUrl = false, bool $cacheUrl = false): ?string
    {
        $mapping = $this->propertyMappingFactory->fromField($obj, $fieldName);

        $fileSystem = $this->mountManager->getFilesystem($mapping->getUploadDestination());

        $uri = $this->storage->resolveUri($obj, $fieldName);

        if (!$uri) {
            return null;
        }

        if ($generateUrl) {

            if ($cacheUrl) {
                return $this->urlGenerator->generate('liip_imagine_cache', [
                    'path' => ltrim($uri, '/'),
                    'filter' => $filter,
                ], UrlGeneratorInterface::ABSOLUTE_URL);
            }

            return $this->cacheManager->generateUrl($uri, $filter);
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
        return $this->projectCache->get('banner_svg_stat', function (ItemInterface $item) {

            $item->expiresAfter(3600);

            try {
                return $this->assetsFilesystem->has('banner.svg');
            } catch (S3Exception $e) {
                return false;
            }
        });
    }

    public function placeholderImage(?string $url, string $filter, string $provider = 'placehold', object|array $obj = null)
    {
        if (!empty($url)) {

            return $url;
        }

        // FIXME Check if Pixabay is configured
        if (null !== $obj && is_callable([ $obj, 'getId' ]) && $this->isPixabayConfigured) {

            return $this->urlGenerator->generate('placeholder_image', [
                'filter' => $filter,
                'hashid'=> $this->hashids12->encode($obj->getId())
            ]);
        }

        $filterConfig = $this->filterManager->getFilterConfiguration()->get($filter);

        [$width, $height] = $filterConfig['filters']['thumbnail']['size'];

        if ($provider === 'placehold') {
            return "//placehold.co/{$width}x{$height}";
        }

        if ($provider === 'picsum') {
            $seed = substr(md5(uniqid(mt_rand(), true)), 0, 8);

            return "//picsum.photos/seed/{$seed}/{$width}/{$height}";
        }
    }
}
