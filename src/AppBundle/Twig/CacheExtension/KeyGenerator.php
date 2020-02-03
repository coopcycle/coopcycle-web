<?php

namespace AppBundle\Twig\CacheExtension;

use Cocur\Slugify\SlugifyInterface;
use Twig\CacheExtension\CacheStrategy\KeyGeneratorInterface;

final class KeyGenerator implements KeyGeneratorInterface
{
    private $slugify;

    public function __construct(SlugifyInterface $slugify)
    {
        $this->slugify = $slugify;
    }

    public function generateKey($value)
    {
        return $this->slugify->slugify(get_class($value)) . '_' . $value->getId() . '_' . $value->getUpdatedAt()->format('U');
    }
}
