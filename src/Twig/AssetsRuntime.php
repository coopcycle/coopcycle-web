<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Unsplash\Client as UnsplashClient;
use AppBundle\Pixabay\Client as PixabayClient;
use Aws\S3\Exception\S3Exception;
use Twig\Extension\RuntimeExtensionInterface;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use SapientPro\ImageComparator\ImageComparator;
use SapientPro\ImageComparator\Strategy\DifferenceHashStrategy;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;

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
        UnsplashClient $unsplash,
        PixabayClient $pixabay,
        Filesystem $restaurantImagesFilesystem,
        UploadHandler $uploadHandler,
        DataManager $dataManager,
        FilterManager $filterManager)
    {
        $this->storage = $storage;
        $this->mountManager = $mountManager;
        $this->propertyMappingFactory = $propertyMappingFactory;
        $this->cacheManager = $cacheManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->urlGenerator = $urlGenerator;
        $this->projectCache = $projectCache;
        $this->unsplash = $unsplash;
        $this->pixabay = $pixabay;
        $this->entityManager = $entityManager;
        $this->restaurantImagesFilesystem = $restaurantImagesFilesystem;
        $this->uploadHandler = $uploadHandler;
        $this->dataManager = $dataManager;
        $this->filterManager = $filterManager;
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

    public function restaurantBanner(LocalBusiness $restaurant)
    {
        $imageName = $restaurant->getBannerImageName();
        if (!empty($imageName)) {

            if (filter_var($imageName, FILTER_VALIDATE_URL)) {
                return $imageName;
            }

            return $this->asset($restaurant, 'bannerImageFile', 'restaurant_banner');
        }

        $existingBanners = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->findBannerImageNames();

        $images = [];
        foreach ($existingBanners as $banner) {

            $obj = ['bannerImageName' => $banner];

            $mapping = $this->propertyMappingFactory->fromField($obj, 'bannerImageFile', LocalBusiness::class);
            $fileSystem = $this->mountManager->getFilesystem($mapping->getUploadDestination());
            $uri = $this->storage->resolveUri($obj, 'bannerImageFile', LocalBusiness::class);
            $url = $this->cacheManager->generateUrl($uri, 'restaurant_banner');

            $url = str_replace('localhost', 'host.docker.internal', $url);

            $images[] = imagecreatefromstring(file_get_contents($url));
        }

        $query = implode(' ', $restaurant->getShopCuisines());
        if (!$query) {
            $query = $restaurant->getShopType();
        }

        $imageComparator = new ImageComparator();
        $imageComparator->setHashStrategy(new DifferenceHashStrategy());

        $page = 1;
        while ($page <= 10) {

            $results = $this->pixabay->search($query, $page);

            foreach ($results as $result) {

                $binary = new Binary(
                    file_get_contents($result['webformatURL']),
                    'image/jpeg',
                    'jpeg'
                );
                $binary = $this->filterManager->applyFilter($binary, 'restaurant_banner');
                $remoteImage = imagecreatefromstring($binary->getContent());

                foreach ($images as $image) {

                    $similarity = $imageComparator->compare(
                        $image,
                        $remoteImage
                    );

                    if ($similarity < 80.0) {

                        // https://stackoverflow.com/questions/40454950/set-symfony-uploaded-file-by-url-input

                        $file = tmpfile();
                        $newfile = stream_get_meta_data($file)['uri'];

                        copy($result['webformatURL'], $newfile);
                        $mimeType = mime_content_type($newfile);
                        $size = filesize($newfile);
                        $finalName = md5(uniqid(rand(), true)) . '.jpg';

                        $uploadedFile = new UploadedFile($newfile, $finalName, $mimeType, $size);

                        $restaurant->setBannerImageFile($uploadedFile);

                        $this->uploadHandler->upload($restaurant, 'bannerImageFile');

                        unlink($newfile);

                        $restaurant->setBannerImageName(
                            $restaurant->getBannerImageFile()->getBasename()
                        );

                        $this->entityManager->flush();

                        return $this->asset($restaurant, 'bannerImageFile', 'restaurant_banner');
                    }
                }
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

    public function placeholderImage(?string $url, string $filter, string $provider = 'placehold')
    {
        if (!empty($url)) {

            return $url;
        }

        $filterConfig = $this->filterManager->getFilterConfiguration()->get($filter);

        [$width, $height] = $filterConfig['filters']['thumbnail']['size'];

        if ($provider === 'placehold') {
            return "//placehold.co/{$width}x{$height}";
        }
    }
}
