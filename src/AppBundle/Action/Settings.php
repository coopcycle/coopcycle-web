<?php

namespace AppBundle\Action;

use AppBundle\Service\SettingsManager;
use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Settings
{
    private $settingsManager;
    private $assetsFilesystem;
    private $country;
    private $locale;

    private $keys = [
        'brand_name',
        'stripe_publishable_key',
        'google_api_key',
        'latlng',
        'currency_code',
    ];

    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        FilterService $imagineFilter,
        $country,
        $locale)
    {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->imagineFilter = $imagineFilter;
        $this->country = $country;
        $this->locale = $locale;
    }

    /**
     * @Route(
     *     path="/settings",
     *     name="api_settings",
     *     methods={"GET"}
     * )
     */
    public function settingsAction(Request $request): JsonResponse
    {
        $data = [
            'country' => $this->country,
            'locale' => $this->locale,
        ];

        foreach ($this->keys as $key) {
            $data[$key] = $this->settingsManager->get($key);
        }

        $companyLogo = $this->settingsManager->get('company_logo');
        if (!empty($companyLogo) && $this->assetsFilesystem->has($companyLogo)) {
            $data['logo'] = $this->imagineFilter->getUrlOfFilteredImage($companyLogo, 'logo_thumbnail');
        }

        if ($request->query->has('format') && 'hash' === $request->query->get('format')) {
            return new JsonResponse(sha1(json_encode($data)));
        }

        return new JsonResponse($data);
    }
}
