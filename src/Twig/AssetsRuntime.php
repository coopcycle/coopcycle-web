<?php

namespace AppBundle\Twig;

use AppBundle\Unsplash\Client as UnsplashClient;
use Aws\S3\Exception\S3Exception;
use Twig\Extension\RuntimeExtensionInterface;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Entity\LocalBusiness;

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
        EntityManagerInterface $entityManager,
        UnsplashClient $unsplash)
    {
        $this->storage = $storage;
        $this->mountManager = $mountManager;
        $this->propertyMappingFactory = $propertyMappingFactory;
        $this->cacheManager = $cacheManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->urlGenerator = $urlGenerator;
        $this->projectCache = $projectCache;
        $this->unsplash = $unsplash;
        $this->entityManager = $entityManager;
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

    public function restaurantBanner($restaurant) {

        if ($restaurant->getBannerImageName()) {

            return $restaurant->getBannerImageName();
        }

        $existingBanners = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->findBannerImageNames();

        $query = implode(' ', $restaurant->getShopCuisines());
        if (!$query) {
            $query = $restaurant->getShopType();
        }

        $page = 1;
        while ($page <= 10) {

            $results = $this->unsplash->search($query, $page);

            $uniqueUrls = array_diff($results, $existingBanners);

            if (!empty($uniqueUrls)) {
                $url = array_shift($uniqueUrls);

                $restaurant->setBannerImageName($url);
                $this->entityManager->flush();

                return $url;
            }

            $page++;
        }

        return '';
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
}
