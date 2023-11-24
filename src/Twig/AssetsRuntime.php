<?php

namespace AppBundle\Twig;

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
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
        HttpClientInterface $unsplashClient,
        EntityManagerInterface $entityManager)
    {
        $this->storage = $storage;
        $this->mountManager = $mountManager;
        $this->propertyMappingFactory = $propertyMappingFactory;
        $this->cacheManager = $cacheManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->urlGenerator = $urlGenerator;
        $this->projectCache = $projectCache;
        $this->unsplashClient = $unsplashClient;
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

        $qb = $this->entityManager->getRepository(LocalBusiness::class)->createQueryBuilder("r");
        $qb->select("r.bannerImageName")->andWhere("r.bannerImageName is not null");
        $existingBanners = $qb->getQuery()->getArrayResult();
        $existingBanners = array_map(function($banner) {
            return $banner["bannerImageName"];
        }, $existingBanners);

        $query = implode(" ", $restaurant->getShopCuisines());
        if (!$query) {
            $query = "restaurant";
        }
        $page = 1;
        $maxRequests = 10;
    
        while ($page <= $maxRequests) {
            $response = $this->unsplashClient->request(
                'GET',
                "search/photos",
                ["query"=> ["query" => $query, "page" => $page, "orientation" => "landscape", "content_filter" => "high"]]
            );
            $data = $response->toArray();
            $results = $data["results"];
            
            if (!empty($results)) {
                $urls = array_map(function ($result) {
                    return $result['urls']['raw'] ?? null;
                }, $results);
                $uniqueUrls = array_values(array_diff(array_filter($urls), $existingBanners));
                if (!empty($uniqueUrls)) {
                    $url = $uniqueUrls[0];
                    $restaurant->setBannerImageName($url);
                    $this->entityManager->flush();
                    return $url;
                }
                return "";
            }
            $page++;
        }
        return "";
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
