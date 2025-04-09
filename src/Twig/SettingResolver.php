<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use AppBundle\Utils\GeoUtils;
use AppBundle\Validator\Constraints\GoogleApiKey;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;
use z4kn4fein\SemVer\Version;

class SettingResolver implements RuntimeExtensionInterface
{
    public function __construct(
        private SettingsManager $settingsManager,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private CacheInterface $cache)
    {}

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

    public function getLatLngBounds(): string
    {
        $latlng = $this->settingsManager->get('latlng');

        [ $lat, $lng ] = explode(',', $latlng);

        return GeoUtils::getLatLngBounds($lat, $lng);
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

        $isGoogleApiKeyValid = $this->cache->get('google_api_key_invalid', function (ItemInterface $item) use ($settings) {

            $item->expiresAfter(
                \DateInterval::createFromDateString('1 day')
            );

            $violations = $this->validator->validate($settings);
            if (count($violations) > 0) {
                $invalidApiKey = array_filter(iterator_to_array($violations), fn ($v) => $v->getCode() === GoogleApiKey::INVALID_API_KEY_ERROR);
                if (count($invalidApiKey) === 1) {
                    return false;
                }
            }

            return true;
        });

        if (!$isGoogleApiKeyValid) {
            $messages[] = $this->translator->trans(id: 'googlemaps.api_key.invalid', domain: 'validators');
        }

        return $messages;
    }

    public function getVersion(): string
    {
        return $this->settingsManager->getVersion();
    }

    public function getGithubReleaseLink(): string
    {
        $versionString = $this->settingsManager->getVersion();

        if ('dev-master' === $versionString) {
            return 'https://github.com/coopcycle/coopcycle-web/releases';
        }

        $version = Version::parse(str_replace('v', '', $versionString));

        return sprintf('https://github.com/coopcycle/coopcycle-web/releases/tag/v%s.%s.0', $version->getMajor(), $version->getMinor());
    }
}
