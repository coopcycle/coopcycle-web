<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Type extends ShopCollection
{
    public string $type;

    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'type' => $this->type,
        ]);
    }

    public function getTitle(): string
    {
        $shopType = LocalBusiness::getTypeForKey($this->type);

        return $this->translator->trans(LocalBusiness::getTransKeyForType($shopType));
    }

    protected function doGetShops(): array
    {
        $shopType = LocalBusiness::getTypeForKey($this->type);

        $typeRepository = $this->repository->withTypeFilter($shopType);

        $items = $typeRepository->findAllForType();

        $iterator = new SortableRestaurantIterator($items, $this->timingRegistry);

        return iterator_to_array($iterator);

        /*

        $typeRepository = $repository->withTypeFilter($type);

        $itemsIds = $cache->get($cacheKey, function (ItemInterface $item) use ($typeRepository, $timingRegistry) {

            $item->expiresAfter(self::EXPIRES_AFTER);

            $items = $typeRepository->findAllForType();

            $iterator = new SortableRestaurantIterator($items, $timingRegistry);
            $items = iterator_to_array($iterator);

            return array_map(fn(LocalBusiness $lb) => $lb->getId(), $items);
        });

        foreach ($itemsIds as $id) {
            // If one of the items can't be found (probably because it's disabled),
            // we invalidate the cache
            if (null === $typeRepository->find($id)) {
                $cache->delete($cacheKey);

                return $this->getItems($repository, $type, $cache, $cacheKey, $timingRegistry);
            }
        }

        $count = count($itemsIds);
        $items = array_map(
            fn(int $id): LocalBusiness => $typeRepository->find($id),
            $itemsIds
        );

        return [ $items, $count ];
        */
    }

    protected function getCacheKeyParts(): array
    {
        return [
            $this->type
        ];
    }
}
