<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use Cocur\Slugify\SlugifyInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class UrlGeneratorRuntime implements RuntimeExtensionInterface
{
    private $urlGenerator;
    private $slugify;

    public function __construct(UrlGeneratorInterface $urlGenerator, SlugifyInterface $slugify)
    {
        $this->urlGenerator = $urlGenerator;
        $this->slugify = $slugify;
    }

    public function localBusinessPath(LocalBusiness $entity, $parameters, $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        $defaultParameters = [
            'id' => $entity->getId(),
            'slug' => $this->slugify->slugify($entity->getName()),
            'type' => $entity->getContext() === Store::class ? 'store' : 'restaurant',
        ];

        return $this->urlGenerator->generate('restaurant', array_merge($defaultParameters, $parameters), $referenceType);
    }
}
