<?php

namespace AppBundle\Action;

use AppBundle\Service\SettingsManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
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
        'stripe_publishable_key',
        'google_api_key',
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
     *     name="api_settings"
     * )
     * @Method("GET")
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

        return new JsonResponse($data);
    }
}
