<?php

namespace AppBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Banner
{
    public $backgroundColor;
    public $colorScheme;
    public $markdown;
    public $link;
}

