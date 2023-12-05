<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use AppBundle\Utils\GeoUtils;
use AppBundle\Validator\Constraints\GoogleApiKey;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class SettingResolver implements RuntimeExtensionInterface
{
    private $settingsManager;

    public function __construct(
        SettingsManager $settingsManager,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator)
    {
        $this->settingsManager = $settingsManager;
        $this->validator = $validator;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
    }

    public function resolveSetting($name)
    {
        return $this->settingsManager->get($name);
    }

    public function getBoundingRect(): string
    {
        $latlng = $this->settingsManager->get('latlng');

        [ $lat, $lng ] = explode(',', $latlng);

        return implode(',', GeoUtils::getViewbox($lat, $lng, 15));
    }

    /**
     * @return string[]
     */
    public function configTest(): array
    {
        $settings = $this->settingsManager->asEntity();

        $messages = [];

        $violations = $this->validator->validate($settings, null, ['groups' => 'mandatory']);
        if (count($violations) > 0) {
            $messages[] = $this->translator->trans('admin.settings.missing_mandatory_settings', [
                '%settings_url%' => $this->urlGenerator->generate('admin_settings')
            ]);
        }

        $violations = $this->validator->validate($settings);
        if (count($violations) > 0) {
            $invalidApiKey = array_filter(iterator_to_array($violations), fn ($v) => $v->getCode() === GoogleApiKey::INVALID_API_KEY_ERROR);
            if (count($invalidApiKey) === 1) {
                $messages[] = $this->translator->trans(id: 'googlemaps.api_key.invalid', domain: 'validators');
            }
        }

        return $messages;
    }
}
