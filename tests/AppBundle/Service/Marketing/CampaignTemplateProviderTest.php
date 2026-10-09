<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Service\Marketing\CampaignTemplateProvider;
use AppBundle\Service\SettingsManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class CampaignTemplateProviderTest extends TestCase
{
    use ProphecyTrait;

    private function translator(): Translator
    {
        // The ambient locale is deliberately English, so a provider built for
        // French has to be asking for French explicitly to get it.
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());

        $translator->addResource('array', [
            'marketing.campaign.template.at_risk.name' => 'We miss you - %segment%',
            'marketing.campaign.template.at_risk.subject' => 'It has been a while',
            'marketing.campaign.template.at_risk.body' => 'Come back to %brand_name%.',
            'rfm.segment.at_risk' => 'At Risk',
        ], 'en', 'messages');

        $translator->addResource('array', [
            'marketing.campaign.template.at_risk.name' => 'Vous nous manquez - %segment%',
            'marketing.campaign.template.at_risk.subject' => 'Cela fait un moment',
            'marketing.campaign.template.at_risk.body' => 'Revenez chez %brand_name%.',
            'rfm.segment.at_risk' => 'Clients à risque',
        ], 'fr', 'messages');

        return $translator;
    }

    private function provider(string $locale): CampaignTemplateProvider
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get(Argument::any())->willReturn(null);
        $settingsManager->get('brand_name')->willReturn('CoopCycle');

        return new CampaignTemplateProvider($this->translator(), $settingsManager->reveal(), $locale);
    }

    /**
     * The subject and body are read by customers, so they follow the
     * language the instance serves -- not whichever one the admin happens to
     * be using the back office in.
     */
    public function testSeedsTheCampaignInTheInstanceLocale(): void
    {
        $provider = $this->provider('fr');

        self::assertSame('Cela fait un moment', $provider->getSubject('at_risk'));
        self::assertStringContainsString('Revenez chez CoopCycle.', $provider->getBodyMjml('at_risk'));
        self::assertSame('Vous nous manquez - Clients à risque', $provider->getName('at_risk'));
    }

    public function testAnEnglishInstanceGetsEnglish(): void
    {
        $provider = $this->provider('en');

        self::assertSame('It has been a while', $provider->getSubject('at_risk'));
        self::assertStringContainsString('Come back to CoopCycle.', $provider->getBodyMjml('at_risk'));
        self::assertSame('We miss you - At Risk', $provider->getName('at_risk'));
    }

    public function testSegmentsWithoutAStrategyGetAnEmptyBody(): void
    {
        $provider = $this->provider('fr');

        self::assertFalse($provider->hasStrategy('promising'));
        self::assertSame('', $provider->getSubject('promising'));
        // Still a usable MJML document, just with nothing written in it.
        self::assertStringContainsString('<mjml>', $provider->getBodyMjml('promising'));
    }

    public function testTheBodyIsAValidMjmlDocument(): void
    {
        $mjml = $this->provider('fr')->getBodyMjml('at_risk');

        self::assertStringStartsWith('<mjml>', trim($mjml));
        self::assertStringContainsString('<mj-body', $mjml);
        self::assertStringContainsString('</mjml>', $mjml);
    }
}
