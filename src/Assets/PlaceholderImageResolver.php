<?php

namespace AppBundle\Assets;

use Hashids\Hashids;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PlaceholderImageResolver
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private FilterManager $filterManager,
        private Hashids $hashids12,
        string $pixabayApiKey)
    {
        $this->isPixabayConfigured = !empty($pixabayApiKey);
    }

    public function resolve(string $filter, string $provider = 'placehold', object|array $obj = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        if (null !== $obj && is_callable([ $obj, 'getId' ]) && $this->isPixabayConfigured) {

            return $this->urlGenerator->generate('placeholder_image', [
                'filter' => $filter,
                'hashid'=> $this->hashids12->encode($obj->getId())
            ], $referenceType);
        }

        $filterConfig = $this->filterManager->getFilterConfiguration()->get($filter);

        [$width, $height] = $filterConfig['filters']['thumbnail']['size'];

        if ($provider === 'placehold') {
            return "//placehold.co/{$width}x{$height}";
        }

        if ($provider === 'picsum') {
            $seed = substr(md5(uniqid(mt_rand(), true)), 0, 8);

            return "//picsum.photos/seed/{$seed}/{$width}/{$height}";
        }
    }
}
