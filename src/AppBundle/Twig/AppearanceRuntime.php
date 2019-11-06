<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\Filesystem;
use Symfony\Component\Asset\Packages;
use Twig\Extension\RuntimeExtensionInterface;

class AppearanceRuntime implements RuntimeExtensionInterface
{
    private $settingsManager;
    private $packages;
    private $assetsFilesystem;

    public function __construct(
        SettingsManager $settingsManager,
        Packages $packages,
        Filesystem $assetsFilesystem,
        string $logoFallback)
    {
        $this->settingsManager = $settingsManager;
        $this->packages = $packages;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->logoFallback = $logoFallback;
    }

    public function logo()
    {
        $companyLogo = $this->settingsManager->get('company_logo');

        if (!empty($companyLogo)) {
            return $this->packages->getUrl(sprintf('images/assets/%s', $companyLogo));
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
