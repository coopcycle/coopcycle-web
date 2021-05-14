<?php


namespace AppBundle\Fixtures\AliceDataFixtures;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Service\SettingsManager;
use Fidry\AliceDataFixtures\ProcessorInterface;

final class SettingsProcessor implements ProcessorInterface
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @inheritdoc
     */
    public function preProcess(string $fixtureId, $object): void
    {
        // do nothing
    }

    /**
     * @inheritdoc
     */
    public function postProcess(string $fixtureId, $object): void
    {
        // do nothing

        if (false === $object instanceof PricingRuleSet) {
            return;
        }

        $this->settingsManager->set('embed.delivery.pricingRuleSet', $object->getId());
        $this->settingsManager->flush();
    }
}
