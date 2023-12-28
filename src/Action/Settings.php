<?php

namespace AppBundle\Action;

use AppBundle\Service\SettingsManager;
use AppBundle\Service\TimeRegistry;
use Aws\S3\Exception\S3Exception;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Service\FilterService;
use Misd\PhoneNumberBundle\Serializer\Normalizer\PhoneNumberNormalizer;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\DeliveryForm;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Hashids\Hashids;

class Settings
{
    private $settingsManager;
    private $assetsFilesystem;
    private $country;
    private $locale;
    private $splitTermsAndConditionsAndPrivacyPolicy;
    private $timeRegistry;
    private $entityManager;
    private $router;
    private $keys = [
        'brand_name',
        'stripe_publishable_key',
        'payment_gateway',
        'mercadopago_publishable_key',
        'latlng',
        'currency_code',
        'administrator_email',
        'guest_checkout_enabled',
        'motto',
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
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $router,
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
        $this->entityManager = $entityManager;
        $this->router = $router;
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

        $deliveryForm = $this->entityManager->getRepository(DeliveryForm::class)->findOneBy(['showHomepage' => true]);

        if ($deliveryForm) {
            $deliveryFormId = $deliveryForm->getId();
            $deliveryFormIdHash = $this->hashids12->encode($deliveryFormId);
            $data['default_delivery_form_url'] = $this->router->generate('embed_delivery_start', ['hashid'=> $deliveryFormIdHash], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $orderConfirmMessage = '';
        if ($this->assetsFilesystem->has('order_confirm.md')) {
            $orderConfirmMessage = $assetsFilesystem->read('order_confirm.md');
        }
        $data['order_confirm_message'] = $orderConfirmMessage;
        
        if ($request->query->has('format') && 'hash' === $request->query->get('format')) {
            return new JsonResponse(sha1(json_encode($data)));
        }

        return new JsonResponse($data);
    }
}
