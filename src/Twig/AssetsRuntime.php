<?php

namespace AppBundle\Twig;

use AppBundle\Assets\PlaceholderImageResolver;
use Twig\Extension\RuntimeExtensionInterface;
use Intervention\Image\ImageManager;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCheckFileExistence;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

class AssetsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private StorageInterface $storage,
        private PropertyMappingFactory $propertyMappingFactory,
        private CacheManager $cacheManager,
        private Filesystem $assetsFilesystem,
        private UrlGeneratorInterface $urlGenerator,
        private CacheInterface $projectCache,
        private PlaceholderImageResolver $placeholderImageResolver)
    {}

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
        $this->propertyMappingFactory->fromField($obj, $fieldName);

        $uri = $this->storage->resolveUri($obj, $fieldName);

        if (!$uri) {
            return '';
        }

        $imageManager = ImageManager::gd();

        $image = $imageManager->read(stream_get_contents($this->storage->resolveStream($obj, $fieldName)));

        return $image->toJpeg()->toDataUri();
    }

    public function hasCustomBanner(): bool
    {
        return $this->projectCache->get('banner_svg_stat', function (ItemInterface $item) {

            $item->expiresAfter(3600);

            try {
                return $this->assetsFilesystem->fileExists('banner.svg');
            } catch (UnableToCheckFileExistence $e) {
                return false;
            }
        });
    }

    public function placeholderImage(?string $url, string $filter, string $provider = 'placehold', object|array|null $obj = null)
    {
        if (!empty($url)) {

            return $url;
        }

        return $this->placeholderImageResolver->resolve($filter, $provider, $obj);
    }
}
