<?php

namespace AppBundle\Twig;

use AppBundle\Service\TagManager;
use Twig\Extension\RuntimeExtensionInterface;

class TagsRuntime implements RuntimeExtensionInterface
{
    public function __construct(private TagManager $tagManager)
    {}

    public function expandTags(array $tags)
    {
        return $this->tagManager->expand($tags);
    }
}

