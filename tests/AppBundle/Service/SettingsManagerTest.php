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
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
            new GatewayResolver('fr'),
            '/path/to/project_dir',
            new ArrayAdapter(),
            new ArrayAdapter()
        );

        $this->assertEquals($expected, $settingsManager->canSendSms());
    }

    private function createSettingsManager(ArrayAdapter $craueConfigCache): SettingsManager
    {
        return new SettingsManager(
            $this->craueConfig->reveal(),
            $this->craueCache->reveal(),
            Setting::class,
            $this->doctrine->reveal(),
            $this->phoneNumberUtil->reveal(),
            'fr',
            $foodtechEnable = true,
            $b2bEnabled = false,
            new GatewayResolver('fr'),
            '/path/to/project_dir',
            new ArrayAdapter(),
            $craueConfigCache
        );
    }

    public function testMissingSettingIsLookedUpOnce()
    {
        $this->craueConfig->get('company_logo')
            ->willThrow(new \RuntimeException('Setting "company_logo" couldn\'t be found.'))
            ->shouldBeCalledTimes(1);

        $craueConfigCache = new ArrayAdapter();

        // Two instances sharing the pool, i.e. two requests
        $this->assertNull($this->createSettingsManager($craueConfigCache)->get('company_logo'));
        $this->assertNull($this->createSettingsManager($craueConfigCache)->get('company_logo'));
    }

    public function testMissingSettingIsLookedUpAgainOnceCreated()
    {
        $craueConfigCache = new ArrayAdapter();

        $this->craueConfig->get('company_logo')
            ->willThrow(new \RuntimeException('Setting "company_logo" couldn\'t be found.'));

        $this->assertNull($this->createSettingsManager($craueConfigCache)->get('company_logo'));

        // Craue caches the value under the setting name when it is created
        $item = $craueConfigCache->getItem('company_logo');
        $item->set('logo.png');
        $craueConfigCache->save($item);

        $this->craueConfig->get('company_logo')->willReturn('logo.png');

        $this->assertEquals('logo.png', $this->createSettingsManager($craueConfigCache)->get('company_logo'));
    }

    public function testSetInvalidatesMissingSetting()
    {
        $craueConfigCache = new ArrayAdapter();
        $settingsManager = $this->createSettingsManager($craueConfigCache);

        $this->craueConfig->get('company_logo')
            ->willThrow(new \RuntimeException('Setting "company_logo" couldn\'t be found.'));

        $this->assertNull($settingsManager->get('company_logo'));
        $this->assertTrue($craueConfigCache->hasItem('missing.company_logo'));

        $this->craueConfig->set('company_logo', 'logo.png')->shouldBeCalled();
        $this->craueConfig->get('company_logo')->willReturn('logo.png');

        $settingsManager->set('company_logo', 'logo.png');

        $this->assertFalse($craueConfigCache->hasItem('missing.company_logo'));
        $this->assertEquals('logo.png', $settingsManager->get('company_logo'));
    }
}
