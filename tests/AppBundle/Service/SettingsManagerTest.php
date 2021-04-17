<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\SettingsManager;
use Craue\ConfigBundle\CacheAdapter\CacheAdapterInterface;
use Craue\ConfigBundle\Util\Config as CraueConfig;
use Craue\ConfigBundle\Entity\Setting;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use AppBundle\Payment\GatewayResolver;

class SettingsManagerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->craueConfig = $this->prophesize(CraueConfig::class);
        $this->craueCache = $this->prophesize(CacheAdapterInterface::class);
        $this->doctrine = $this->prophesize(ManagerRegistry::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);
    }

    public function canSendSmsProvider()
    {
        return [
            [
                false,
                false,
                null,
                null,
                'fr'
            ],
            [
                false,
                true,
                'foo',
                null,
                'fr'
            ],
            [
                false,
                true,
                'mailjet',
                null,
                'fr'
            ],
            [
                false,
                true,
                'mailjet',
                json_encode(['foo' => 'bar']),
                'fr'
            ],
            [
                true,
                true,
                'mailjet',
                json_encode(['api_token' => 'bar']),
                'fr'
            ],
            [
                false,
                true,
                'mailjet',
                json_encode(['api_token' => 'bar']),
                'gb'
            ],
        ];
    }

    /**
     * @dataProvider canSendSmsProvider
     */
    public function testCanSendSms($expected, $smsEnabled, $smsGateway, $smsGatewayConfig, $country)
    {
        $this->craueConfig->get('sms_enabled')->willReturn($smsEnabled);
        $this->craueConfig->get('sms_gateway')->willReturn($smsGateway);
        $this->craueConfig->get('sms_gateway_config')->willReturn($smsGatewayConfig);

        $settingsManager = new SettingsManager(
            $this->craueConfig->reveal(),
            $this->craueCache->reveal(),
            Setting::class,
            $this->doctrine->reveal(),
            $this->phoneNumberUtil->reveal(),
            $country,
            $foodtechEnable = true,
            $b2bEnabled = false,
            new GatewayResolver('fr')
        );

        $this->assertEquals($expected, $settingsManager->canSendSms());
    }
}
