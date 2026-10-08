<?php

namespace Tests\AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use AppBundle\Twig\AppearanceRuntime;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class AppearanceRuntimeTest extends TestCase
{
    use ProphecyTrait;

    private $settingsManager;
    private $assetsFilesystem;
    private $appCache;

    public function setUp(): void
    {
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->assetsFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->appCache = new ArrayAdapter();
    }

    private function createRuntime(?Filesystem $assetsFilesystem = null): AppearanceRuntime
    {
        return new AppearanceRuntime(
            $this->settingsManager->reveal(),
            $assetsFilesystem ?? $this->assetsFilesystem,
            $this->prophesize(FilterService::class)->reveal(),
            $this->appCache,
            '/path/to/logo.png'
        );
    }

    public function testBannerBackgroundUrlWithoutSetting()
    {
        $this->settingsManager->get('banner_background_image')->willReturn(null);

        $this->assertNull($this->createRuntime()->getBannerBackgroundUrl());
    }

    public function testBannerBackgroundUrlIsVersioned()
    {
        $this->settingsManager->get('banner_background_image')->willReturn('banner_background.jpg');
        $this->assetsFilesystem->write('banner_background.jpg', 'foo');

        $lastModified = $this->assetsFilesystem->lastModified('banner_background.jpg');

        $this->assertEquals(
            sprintf('/assets/banner_background?v=%d', $lastModified),
            $this->createRuntime()->getBannerBackgroundUrl()
        );
    }

    public function testBannerBackgroundUrlWithMissingFile()
    {
        $this->settingsManager->get('banner_background_image')->willReturn('banner_background.jpg');

        $this->assertNull($this->createRuntime()->getBannerBackgroundUrl());
    }

    public function testBannerBackgroundUrlIsCached()
    {
        $this->settingsManager->get('banner_background_image')->willReturn('banner_background.jpg');
        $this->assetsFilesystem->write('banner_background.jpg', 'foo');

        $url = $this->createRuntime()->getBannerBackgroundUrl();

        // The storage is not called anymore
        $this->assertEquals($url, $this->createRuntime(new Filesystem(new InMemoryFilesystemAdapter()))->getBannerBackgroundUrl());

        $this->appCache->delete(AppearanceRuntime::BANNER_BACKGROUND_URL_CACHE_KEY);

        $this->assertNull($this->createRuntime(new Filesystem(new InMemoryFilesystemAdapter()))->getBannerBackgroundUrl());
    }
}
