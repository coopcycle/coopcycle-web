<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RestaurantDecorator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $appCache,
        private TranslatorInterface $translator)
    {
    }

    public function getTags(LocalBusiness $restaurant): array
    {
        $cacheKey = sprintf('twig.restaurant.%s.tags', $restaurant->getId());

        return $this->appCache->get($cacheKey, function (ItemInterface $item) use ($restaurant) {

            $item->expiresAfter(60 * 5);

            $tags = [];
            foreach ($restaurant->getServesCuisine() as $cuisine) {
                $tags[] = $this->translator->trans($cuisine->getName(), [], 'cuisines');
            }

            return $tags;
        });
    }

    public function getBadges(LocalBusiness $restaurant): array
    {
        $badges = [];

        if ($restaurant->isExclusive()) {
            $badges[] = 'exclusive';
        }

        if ($restaurant->isDepositRefundEnabled() || $restaurant->isLoopeatEnabled()) {
            $badges[] = 'zero-waste';
        }

        if ($restaurant->supportsEdenred()) {
            $badges[] = 'edenred';
        }

        if ($restaurant->isVytalEnabled()) {
            $badges[] = 'vytal';
        }

        $newRestaurantIds = $this->appCache->get('twig.new_restaurants.ids', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60 * 24);

            return $this->entityManager->getRepository(LocalBusiness::class)->findNewRestaurantIds();
        });

        if (in_array($restaurant->getId(), $newRestaurantIds)) {
            $badges[] = 'new';
        }

        return $badges;
    }
}
