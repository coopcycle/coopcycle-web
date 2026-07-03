<?php

namespace AppBundle\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::TARGET_METHOD)]
final class HideDisabled
{
    /** @param string[] $classes */
    public function __construct(private array $classes = [])
    {}

    public function getClasses(): array
    {
        return $this->classes;
    }
}

