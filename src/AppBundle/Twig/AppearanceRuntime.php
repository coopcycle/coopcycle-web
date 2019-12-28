<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Service\FilterService;
use Twig\Extension\RuntimeExtensionInterface;

class AppearanceRuntime implements RuntimeExtensionInterface
{
    private $settingsManager;
    private $assetsFilesystem;

    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        FilterService $imagineFilter,
        string $logoFallback)
    {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->imagineFilter = $imagineFilter;
        $this->logoFallback = $logoFallback;
    }

    public function logo()
    {
        $companyLogo = $this->settingsManager->get('company_logo');

        if (!empty($companyLogo) && $this->assetsFilesystem->has($companyLogo)) {

            return $this->imagineFilter->getUrlOfFilteredImage($companyLogo, 'logo_thumbnail');
        }
    }

    public function companyLogo()
    {
        $companyLogo = $this->settingsManager->get('company_logo');

        if (!empty($companyLogo) && $this->assetsFilesystem->has($companyLogo)) {

            return (string) ImageManagerStatic::make($this->assetsFilesystem->read($companyLogo))->encode('data-url');
        }

        return (string) ImageManagerStatic::make(file_get_contents($this->logoFallback))->encode('data-url');
    }
}
