<?php

namespace AppBundle\Twig\CacheExtension;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class KeyGenerator implements RuntimeExtensionInterface
{
    public function __construct(
        private SlugifyInterface $slugify,
        private EntityManagerInterface $entityManager)
    {}

    public function generateKey($value, $annotation)
    {
        $className = $this->entityManager->getClassMetadata(get_class($value))->getName();

        $key =
            $this->slugify->slugify($className) . '_' . $value->getId() . '_' . $value->getUpdatedAt()->format('U');

        return $annotation . '__GCS__' . $key;
    }
}
