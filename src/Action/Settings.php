<?php

namespace AppBundle\Action;

use AppBundle\Service\SettingsManager;
use AppBundle\Service\TimeRegistry;
use Aws\S3\Exception\S3Exception;
use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Service\FilterService;
use Misd\PhoneNumberBundle\Serializer\Normalizer\PhoneNumberNormalizer;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Persistence\ManagerRegistry;
use AppBundle\Entity\DeliveryForm;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Hashids\Hashids;
use Exception;

class Settings
{
    private $settingsManager;
    private $assetsFilesystem;
    private $country;
    private $locale;
    private $splitTermsAndConditionsAndPrivacyPolicy;
    private $timeRegistry;
    private $doctrine;
    private $router;
    private $secret;

    private $keys = [
        'brand_name',
        'stripe_publishable_key',
        'payment_gateway',
        'mercadopago_publishable_key',
        'google_api_key',
        'latlng',
        'currency_code',
        'administrator_email',
        'guest_checkout_enabled',
    ];

    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        FilterService $imagineFilter,
        PhoneNumberNormalizer $phoneNumberNormalizer,
        TimeRegistry $timeRegistry,
        $country,
        $locale,
        $splitTermsAndConditionsAndPrivacyPolicy,
        ManagerRegistry $doctrine,
        UrlGeneratorInterface $router,
        string $secret,
        Hashids $hashids12)
    {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->imagineFilter = $imagineFilter;
        $this->phoneNumberNormalizer = $phoneNumberNormalizer;
        $this->timeRegistry = $timeRegistry;
        $this->country = $country;
        $this->locale = $locale;
        $this->splitTermsAndConditionsAndPrivacyPolicy = (bool) $splitTermsAndConditionsAndPrivacyPolicy;
        $this->doctrine = $doctrine;
        $this->router = $router;
        $this->secret = $secret;
        $this->hashids12 = $hashids12;
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
            'split_terms_and_conditions_and_privacy_policy' => $this->splitTermsAndConditionsAndPrivacyPolicy,
        ];

        foreach ($this->keys as $key) {
            $data[$key] = $this->settingsManager->get($key);
        }

        try {
            $companyLogo = $this->settingsManager->get('company_logo');
            if (!empty($companyLogo) && $this->assetsFilesystem->has($companyLogo)) {
                $data['logo'] = $this->imagineFilter->getUrlOfFilteredImage($companyLogo, 'logo_thumbnail');
            }
        } catch (S3Exception $e) {
            // TODO Log error
        }

        $phoneNumber = $this->settingsManager->get('phone_number');
        if ($phoneNumber) {
            $data['phone_number'] = $this->phoneNumberNormalizer->normalize($phoneNumber);
        }

        $data['average_preparation_time'] = $this->timeRegistry->getAveragePreparationTime();
        $data['average_shipping_time'] = $this->timeRegistry->getAverageShippingTime();
        $data['default_delivery_form_url'] = $this->router->generate('embed_delivery_start', ['hashid'=> $this->hashids12->encode($this->doctrine->getRepository(DeliveryForm::class)->findOneBy(['showHomepage' => true])->getId())], UrlGeneratorInterface::ABSOLUTE_URL);
        
        return new JsonResponse($data);
    }
}
