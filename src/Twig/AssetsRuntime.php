<?php

namespace AppBundle\Twig;

use AppBundle\Assets\PlaceholderImageResolver;
use Aws\S3\Exception\S3Exception;
use Twig\Extension\RuntimeExtensionInterface;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

class AssetsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        StorageInterface $storage,
        PropertyMappingFactory $propertyMappingFactory,
        CacheManager $cacheManager,
        Filesystem $assetsFilesystem,
        UrlGeneratorInterface $urlGenerator,
        CacheInterface $projectCache,
        PlaceholderImageResolver $placeholderImageResolver)
    {
        $this->storage = $storage;
        $this->propertyMappingFactory = $propertyMappingFactory;
        $this->cacheManager = $cacheManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->urlGenerator = $urlGenerator;
        $this->projectCache = $projectCache;
        $this->placeholderImageResolver = $placeholderImageResolver;
    }

    public function asset($obj, string $fieldName, string $filter, bool $generateUrl = false, bool $cacheUrl = false): ?string
    {
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

        $uri = $this->storage->resolveUri($obj, $fieldName);

        if (!$uri) {
            return '';
        }

        return (string) ImageManagerStatic::make(
            stream_get_contents($this->storage->resolveStream($obj, $fieldName))
        )->encode('data-url');
    }

    public function hasCustomBanner(): bool
    {
        return $this->projectCache->get('banner_svg_stat', function (ItemInterface $item) {

            $item->expiresAfter(3600);

            try {
                return $this->assetsFilesystem->fileExists('banner.svg');
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

        return $this->placeholderImageResolver->resolve($filter, $provider, $obj);
    }
}
