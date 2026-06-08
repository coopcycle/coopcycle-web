<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\HomepageOutput;
use AppBundle\Service\SettingsManager;

class HomepagePublishedProvider implements ProviderInterface
{
    public function __construct(private SettingsManager $settingsManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HomepageOutput
    {
        $published = $this->settingsManager->getBoolean('homepage_published');

        return new HomepageOutput($published);
    }
}
