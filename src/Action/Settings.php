<?php

namespace AppBundle\Action;

use AppBundle\Service\SettingsManager;
use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Service\FilterService;
use Misd\PhoneNumberBundle\Serializer\Normalizer\PhoneNumberNormalizer;
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
        'payment_gateway',
        'mercadopago_publishable_key',
        'mercadopago_access_token',
        'google_api_key',
        'latlng',
        'currency_code',
        'administrator_email',
    ];

    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        FilterService $imagineFilter,
        PhoneNumberNormalizer $phoneNumberNormalizer,
        $country,
        $locale)
    {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->imagineFilter = $imagineFilter;
        $this->phoneNumberNormalizer = $phoneNumberNormalizer;
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

        $phoneNumber = $this->settingsManager->get('phone_number');
        if ($phoneNumber) {
            $data['phone_number'] = $this->phoneNumberNormalizer->normalize($phoneNumber);
        }

        if ($request->query->has('format') && 'hash' === $request->query->get('format')) {
            return new JsonResponse(sha1(json_encode($data)));
        }

        return new JsonResponse($data);
    }
}
