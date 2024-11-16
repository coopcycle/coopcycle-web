<?php

namespace AppBundle\Twig;

use Sonata\SeoBundle\Seo\SeoPageInterface;
use Twig\Extension\RuntimeExtensionInterface;

class PageTitlePrefixResolver implements RuntimeExtensionInterface
{

    public function __construct(
        private readonly SeoPageInterface $seoPage
    )
    {
    }

    public function addTitlePrefix(string $prefix): void
    {
        $this->seoPage->addTitlePrefix($prefix);
    }

}
