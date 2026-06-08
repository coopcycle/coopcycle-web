<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Intervention\Image\ImageManager;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToReadFile;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\RuntimeExtensionInterface;

class AppearanceRuntime implements RuntimeExtensionInterface
{
    private $settingsManager;
    private $assetsFilesystem;
    private $imagineFilter;
    private $appCache;
    private $logoFallback;
    private bool $isDemo;

    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        FilterService $imagineFilter,
        CacheInterface $appCache,
        string $logoFallback,
        #[Autowire('%is_demo%')] bool $isDemo = false,
    ) {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->imagineFilter = $imagineFilter;
        $this->appCache = $appCache;
        $this->logoFallback = $logoFallback;
        $this->isDemo = $isDemo;
    }

    public function logo()
    {
        $companyLogo = $this->settingsManager->get('company_logo');

        try {
            if (!empty($companyLogo) && $this->assetsFilesystem->fileExists($companyLogo)) {

                return $this->imagineFilter->getUrlOfFilteredImage($companyLogo, 'logo_thumbnail');
            }
        } catch (UnableToCheckFileExistence|UnableToReadFile $e) {}
    }

    public function companyLogo()
    {
        return $this->appCache->get('content.company_logo.base_64', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60 * 24);

            $imageManager = ImageManager::gd();

            try {

                $companyLogo = $this->settingsManager->get('company_logo');

                if (!empty($companyLogo) && $this->assetsFilesystem->fileExists($companyLogo)) {
                    $image = $imageManager->read($this->assetsFilesystem->read($companyLogo));
                } else {
                    $image = $imageManager->read(file_get_contents($this->logoFallback));
                }

            } catch (UnableToCheckFileExistence|UnableToReadFile $e) {
                $image = $imageManager->read(file_get_contents($this->logoFallback));
            }

            return $image->toPng()->toDataUri();
        });
    }

    public function hasAboutUs()
    {
        return $this->appCache->get('content.about_us.exists', function (ItemInterface $item) {
            try {

                $exists = $this->assetsFilesystem->fileExists('about_us.md');

                $item->expiresAfter(60 * 60 * 24);

                return $exists;

            } catch (UnableToCheckFileExistence|UnableToReadFile $e) {

                $item->expiresAfter(60 * 5);

                return false;
            }
        });
    }

    private function generateDemoTheme(): array
    {
        $hue = random_int(0, 359);
        $complementaryHue = ($hue + 180) % 360;

        return [
            'primary'           => sprintf('oklch(0.45 0.20 %d)', $hue),
            'primary-content'   => sprintf('oklch(0.95 0.03 %d)', $hue),
            'secondary'         => sprintf('oklch(0.50 0.18 %d)', $complementaryHue),
            'secondary-content' => sprintf('oklch(0.95 0.02 %d)', $complementaryHue),
        ];
    }

    public function getBannerBackgroundTone(): string
    {
        $filename = $this->settingsManager->get('banner_background_image');
        if (!$filename) {
            return 'dark';
        }

        return $this->appCache->get('banner_background_image_tone', function (ItemInterface $item) use ($filename) {
            $item->expiresAfter(60 * 60 * 24);

            try {
                $content = $this->assetsFilesystem->read($filename);
            } catch (UnableToReadFile $e) {
                return 'dark';
            }

            $img = imagecreatefromstring($content);
            if (!$img) {
                return 'dark';
            }

            if (!imageistruecolor($img)) {
                imagepalettetotruecolor($img);
            }

            $width = imagesx($img);
            $height = imagesy($img);
            $steps = 10;
            $totalLuminance = 0.0;

            for ($i = 0; $i < $steps; $i++) {
                for ($j = 0; $j < $steps; $j++) {
                    $x = (int)($width * ($i + 0.5) / $steps);
                    $y = (int)($height * ($j + 0.5) / $steps);
                    $rgba = imagecolorat($img, $x, $y);
                    $a = ($rgba >> 24) & 0x7F; // 0=opaque, 127=transparent
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    $alpha = $a / 127.0;
                    $r = (int)($r * (1 - $alpha) + 255 * $alpha);
                    $g = (int)($g * (1 - $alpha) + 255 * $alpha);
                    $b = (int)($b * (1 - $alpha) + 255 * $alpha);
                    $totalLuminance += (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                }
            }

            imagedestroy($img);

            return ($totalLuminance / ($steps * $steps)) >= 0.5 ? 'light' : 'dark';
        });
    }

    public function getBannerBackgroundUrl(): ?string
    {
        $filename = $this->settingsManager->get('banner_background_image');
        if (!$filename) {
            return null;
        }
        try {
            if (!$this->assetsFilesystem->fileExists($filename)) {
                return null;
            }
        } catch (UnableToCheckFileExistence|UnableToReadFile) {
            return null;
        }
        return '/assets/banner_background';
    }

    public function getTheme()
    {
        if ($this->isDemo) {
            return $this->appCache->get('demo.theme', function (ItemInterface $item) {
                $item->expiresAfter(60 * 60 * 24);
                return $this->generateDemoTheme();
            });
        }

        $theme = $this->settingsManager->get('theme');

        if ($theme) {
            return json_decode($theme, true);
        }

        return [];
    }
}
