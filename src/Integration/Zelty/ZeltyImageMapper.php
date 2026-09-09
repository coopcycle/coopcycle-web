<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads a dish/menu's image from Zelty and attaches it to the matching
 * Sylius Product, replacing it only when the source image actually changed.
 *
 * Zelty gives every item's image as a plain URL (e.g.
 * https://media.zelty.fr/images/964/c7e41.png) — there's no ETag/Last-Modified
 * contract to rely on, so "changed" is decided by comparing a sha256 of the
 * downloaded bytes against the checksum stored on the last-imported image.
 */
class ZeltyImageMapper
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Import (or refresh) a product's image from a Zelty item's img URL.
     *
     * A missing/unreachable image is logged and skipped rather than failing
     * the whole catalog import — one bad image shouldn't block every dish.
     */
    public function importImage(Product $product, ?string $imageUrl): void
    {
        if (!$imageUrl) {
            return;
        }

        try {
            $response = $this->httpClient->request('GET', $imageUrl);
            $contents = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            $this->logger?->warning(sprintf(
                'Failed to download Zelty image "%s" for product "%s": %s',
                $imageUrl, $product->getCode(), $e->getMessage()
            ));

            return;
        }

        $checksum = hash('sha256', $contents);

        $existingImage = $this->findZeltyManagedImage($product);

        // Same bytes as last sync: skip the write entirely instead of
        // re-uploading an identical file to storage on every import.
        if ($existingImage !== null && $existingImage->getZeltyChecksum() === $checksum) {
            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'zelty_image_');
        file_put_contents($tmpPath, $contents);

        // UploadedFile, not the plain File the setImageFile() signature accepts:
        // Vich's default namer (vich_uploader.namer_uniqid) requires
        // getClientOriginalName() to derive the stored file's extension, which
        // only UploadedFile (or Vich's own ReplacingFile) exposes. $test: true
        // skips the is_uploaded_file() check, since this file didn't come from
        // an HTTP upload.
        $originalName = basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'image');
        $file = new UploadedFile($tmpPath, $originalName, null, null, true);

        $image = $existingImage ?? new ProductImage();
        $image->setRatio('1:1');
        $image->setZeltyChecksum($checksum);
        // Triggers VichUploaderBundle's listener on flush, which moves the
        // file into managed storage and — for an existing image — deletes
        // the previous file it replaces.
        $image->setImageFile($file);

        if ($existingImage === null) {
            $product->addImage($image);
        }

        $this->em->persist($image);
    }

    /**
     * The image this mapper itself is managing for the product, if any.
     *
     * Never assume it's the first image in the collection — a product can
     * carry other, manually-added images too (e.g. through the admin's own
     * upload form), and picking the wrong one would silently leave it
     * untouched while creating a second, redundant Zelty-managed image.
     */
    private function findZeltyManagedImage(Product $product): ?ProductImage
    {
        foreach ($product->getImages() as $image) {
            if ($image->getZeltyChecksum() !== null) {
                return $image;
            }
        }

        return null;
    }
}
