<?php

namespace AppBundle\Twig;

use AppBundle\Service\EmailTemplateManager;
use Twig\Extension\RuntimeExtensionInterface;

class EmailTemplateRuntime implements RuntimeExtensionInterface
{
    public function __construct(private EmailTemplateManager $emailTemplateManager) {}

    public function getFragment(string $type, string $locale): string
    {
        return $this->emailTemplateManager->getFragment($type, $locale);
    }
}
