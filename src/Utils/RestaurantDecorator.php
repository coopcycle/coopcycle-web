<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Promotion\Checker\Eligibility\PromotionEligibilityCheckerInterface;
use Sylius\Component\Promotion\Checker\Eligibility\PromotionCouponEligibilityCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RestaurantDecorator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $appCache,
        private TranslatorInterface $translator,
        private PromotionEligibilityCheckerInterface $promotionExpirationChecker,
        private PromotionCouponEligibilityCheckerInterface $promotionCouponExpirationChecker)
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

            // Make sure the tags are sorted alphabetically
            sort($tags);

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

        if ($restaurant->hasFeaturedPromotion()) {
            $featuredPromotion = $restaurant->getFeaturedPromotion();

            $isEligible = false;
            if ($featuredPromotion instanceof PromotionCouponInterface) {
                $isEligible = $this->promotionCouponExpirationChecker->isEligible(new Order(), $featuredPromotion);
            } else {
                $isEligible = $this->promotionExpirationChecker->isEligible(new Order(), $featuredPromotion);
            }

            if ($isEligible) {
                $badges[] = 'promotion';
            }
        }

        return $badges;
    }
}
