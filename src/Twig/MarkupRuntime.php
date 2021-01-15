<?php

namespace AppBundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MarkupRuntime implements RuntimeExtensionInterface
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function parse($text)
    {
        if (preg_match('/(#task\/([0-9]+))/', $text, $matches)) {
            $text = str_replace($matches[1], '#'.$matches[2], $text);
        }

        if (preg_match('/(#order\/([0-9]+))/', $text, $matches)) {
            $id = $matches[2];
            $href = $this->urlGenerator->generate('admin_order', ['id' => $id]);
            $link = sprintf('<a href="%s">%s</a>', $href, '#'.$id);
            $text = str_replace($matches[1], $link, $text);
        }

        // TODO Parse users with @

        return $text;
    }
}
