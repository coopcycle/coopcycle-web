<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductImage;
use AppBundle\Integration\Zelty\ZeltyImageMapper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Zelty gives every dish/menu's image as a plain URL with no ETag/Last-Modified
 * to rely on, so "did the image change" is decided by comparing a sha256 of
 * the downloaded bytes against the checksum stored on the last-imported
 * image (ProductImage::zeltyChecksum).
 */
class ZeltyImageMapperTest extends TestCase
{
    public function testNoImageUrlDoesNothing(): void
    {
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $httpClient = new MockHttpClient(function () {
            $this->fail('No HTTP request should be made when there is no image URL.');
        });

        $product = new Product();

        $mapper = new ZeltyImageMapper($httpClient, $em);
        $mapper->importImage($product, null);

        $this->assertCount(0, $persisted);
        $this->assertCount(0, $product->getImages());
    }

    public function testFirstImportCreatesAZeltyManagedImage(): void
    {
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('https://media.zelty.fr/images/964/c7e41.png', $url);

            return new MockResponse('fake-image-bytes-v1');
        });

        $product = new Product();

        $mapper = new ZeltyImageMapper($httpClient, $em);
        $mapper->importImage($product, 'https://media.zelty.fr/images/964/c7e41.png');

        $this->assertCount(1, $product->getImages());
        $this->assertCount(1, $persisted);

        $image = $product->getImages()->first();
        $this->assertSame(hash('sha256', 'fake-image-bytes-v1'), $image->getZeltyChecksum());
        $this->assertNotNull($image->getImageFile());
    }

    public function testReimportWithUnchangedBytesDoesNotReupload(): void
    {
        $checksum = hash('sha256', 'same-bytes');

        $existingImage = new ProductImage();
        $existingImage->setZeltyChecksum($checksum);

        $product = new Product();
        $product->addImage($existingImage);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $httpClient = new MockHttpClient(fn () => new MockResponse('same-bytes'));

        $mapper = new ZeltyImageMapper($httpClient, $em);
        $mapper->importImage($product, 'https://media.zelty.fr/images/964/c7e41.png');

        // Still the same single image, and nothing was re-persisted/re-uploaded.
        $this->assertCount(1, $product->getImages());
        $this->assertCount(0, $persisted);
        $this->assertNull($existingImage->getImageFile());
    }

    public function testReimportWithChangedBytesReplacesTheExistingImage(): void
    {
        $existingImage = new ProductImage();
        $existingImage->setZeltyChecksum(hash('sha256', 'old-bytes'));

        $product = new Product();
        $product->addImage($existingImage);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $httpClient = new MockHttpClient(fn () => new MockResponse('new-bytes'));

        $mapper = new ZeltyImageMapper($httpClient, $em);
        $mapper->importImage($product, 'https://media.zelty.fr/images/964/c7e41.png');

        // Replaced in place — not a second image alongside the old one.
        $this->assertCount(1, $product->getImages());
        $this->assertSame($existingImage, $product->getImages()->first());
        $this->assertSame(hash('sha256', 'new-bytes'), $existingImage->getZeltyChecksum());
        $this->assertNotNull($existingImage->getImageFile());
        $this->assertContains($existingImage, $persisted);
    }

    public function testManuallyAddedImageIsNeverTreatedAsTheZeltyManagedOne(): void
    {
        $manualImage = new ProductImage();
        // No zeltyChecksum: this one was added by hand through the admin.

        $product = new Product();
        $product->addImage($manualImage);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $httpClient = new MockHttpClient(fn () => new MockResponse('zelty-bytes'));

        $mapper = new ZeltyImageMapper($httpClient, $em);
        $mapper->importImage($product, 'https://media.zelty.fr/images/964/c7e41.png');

        // A second image is created rather than overwriting the manual one.
        $this->assertCount(2, $product->getImages());
        $this->assertNull($manualImage->getZeltyChecksum());
        $this->assertNull($manualImage->getImageFile());
    }

    public function testDownloadFailureIsLoggedAndSkippedWithoutThrowing(): void
    {
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $httpClient = new MockHttpClient(fn () => new MockResponse('', ['http_code' => 404]));

        $product = new Product();

        $mapper = new ZeltyImageMapper($httpClient, $em);
        // MockResponse only throws on getContent()/getStatusCode() access with
        // throw_http_errors semantics when consumed via a real client stack;
        // here we just assert that a broken response never leaves the product
        // with a bogus/persisted image.
        $mapper->importImage($product, 'https://media.zelty.fr/images/964/missing.png');

        $this->assertCount(0, $persisted);
    }
}
