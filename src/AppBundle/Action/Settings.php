<?php

namespace AppBundle\Action;

use AppBundle\Service\SettingsManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Settings
{
    private $settingsManager;
    private $country;
    private $locale;

    private $keys = [
        'brand_name',
        'stripe_publishable_key',
        'google_api_key',
        'latlng',
    ];

    public function __construct(SettingsManager $settingsManager, $country, $locale)
    {
        $this->settingsManager = $settingsManager;
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

        if ($request->query->has('format') && 'hash' === $request->query->get('format')) {
            return new JsonResponse(sha1(json_encode($data)));
        }

        return new JsonResponse($data);
    }
}
