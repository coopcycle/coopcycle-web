<?php

namespace AppBundle\Twig\CacheExtension;

use Cocur\Slugify\SlugifyInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class KeyGenerator implements RuntimeExtensionInterface
{
    private $slugify;

    public function __construct(SlugifyInterface $slugify)
    {
        $this->slugify = $slugify;
    }

    public function generateKey($value, $annotation)
    {
        $key =
            $this->slugify->slugify(get_class($value)) . '_' . $value->getId() . '_' . $value->getUpdatedAt()->format('U');

        return $annotation . '__GCS__' . $key;
    }
}
