<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\HomepageInput;
use AppBundle\Api\Dto\HomepageOutput;
use AppBundle\Service\SettingsManager;

class HomepagePublishedProcessor implements ProcessorInterface
{
    public function __construct(private SettingsManager $settingsManager)
    {}

    /**
     * @param HomepageInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): HomepageOutput
    {
        $this->settingsManager->set('homepage_published', $data->published ? '1' : '0');
        $this->settingsManager->flush();

        return new HomepageOutput($data->published);
    }
}
