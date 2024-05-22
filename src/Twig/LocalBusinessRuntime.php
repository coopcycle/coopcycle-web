<?php

namespace AppBundle\Twig;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Entity\BusinessRestaurantGroupRestaurantMenu;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Entity\Zone;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TimingRegistry;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\RestaurantDecorator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;
use libphonenumber\PhoneNumber;

class LocalBusinessRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        TranslatorInterface $translator,
        SerializerInterface $serializer,
        LocalBusinessRepository $repository,
        CacheInterface $projectCache,
        EntityManagerInterface $entityManager,
        TimingRegistry $timingRegistry,
        RestaurantDecorator $restaurantDecorator,
        BusinessContext $businessContext,
        SettingsManager $settingsManager)
    {
        $this->translator = $translator;
        $this->serializer = $serializer;
        $this->repository = $repository;
        $this->projectCache = $projectCache;
        $this->entityManager = $entityManager;
        $this->timingRegistry = $timingRegistry;
        $this->restaurantDecorator = $restaurantDecorator;
        $this->businessContext = $businessContext;
        $this->settingsManager = $settingsManager;
    }

    /**
     * @param string|LocalBusiness $entityOrText
     * @return string
     */
    public function type($entityOrText): ?string
    {
        $type = $entityOrText instanceof LocalBusiness ? $entityOrText->getType() : $entityOrText;

        if (Store::isValid($type)) {
            foreach (Store::values() as $value) {
                if ($value->getValue() === $type) {

                    return $this->translator->trans(sprintf('store.%s', $value->getKey()));
                }
            }
        }

        foreach (FoodEstablishment::values() as $value) {
            if ($value->getValue() === $type) {

                return $this->translator->trans(sprintf('food_establishment.%s', $value->getKey()));
            }
        }

        return '';
    }

    public function seo(LocalBusiness $entity): array
    {
        return $this->serializer->normalize($entity, 'jsonld', [
            'resource_class' => LocalBusiness::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['restaurant_seo', 'address']
        ]);
    }

    public function delayForHumans(int $delay, $locale): string
    {
        Carbon::setLocale($locale);

        $now = Carbon::now();
        $future = clone $now;
        $future->addMinutes($delay);

        return $now->diffForHumans($future, ['syntax' => CarbonInterface::DIFF_ABSOLUTE]);
    }

    public function restaurantsSuggestions(): array
    {
        return $this->projectCache->get('restaurant.suggestions', function (ItemInterface $item) {

            $item->expiresAfter(60 * 5);

            $qb = $this->repository->createQueryBuilder('r');
            $qb->andWhere('r.enabled = :enabled');
            $qb->setParameter('enabled', true);

            $restaurants = $qb->getQuery()->getResult();

            $suggestions = [];
            foreach ($restaurants as $restaurant) {
                $suggestions[] = [
                    'id' => $restaurant->getId(),
                    'name' => $restaurant->getName(),
                ];
            }

            return $suggestions;
        });
    }

    public function getCheckoutSuggestions(OrderInterface $order)
    {
        $restaurants = $order->getRestaurants();

        $suggestions = [];

        if (count($restaurants) === 1) {
            $restaurant = $restaurants->current();
            if ($restaurant->belongsToHub()) {
                $suggestions[] = [
                    'type' => 'CONTINUE_SHOPPING_HUB',
                    'hub'  => $restaurant->getHub(),
                ];
            }
        }

        return $suggestions;
    }

    public function getZoneNames(): array
    {
        $names = [];

        $zones =
            $this->entityManager->getRepository(Zone::class)->findAll();

        foreach ($zones as $zone) {
            $names[] = $zone->getName();
        }

        return $names;
    }

    /**
     * @param string|LocalBusiness $entityOrText
     * @return string
     */
    public function typeKey($entityOrText): ?string
    {
        $type = $entityOrText instanceof LocalBusiness ? $entityOrText->getType() : $entityOrText;

        return LocalBusiness::getKeyForType($type);
    }

    public function shouldShowPreOrder(LocalBusiness $entity): bool
    {
        $timeInfo = $this->timingRegistry->getForObject($entity);

        if (empty($timeInfo)) {

            return false;
        }

        if (!isset($timeInfo['range'])) {

            return false;
        }

        if (!is_array($timeInfo['range']) || count($timeInfo['range']) !== 2) {

            return false;
        }

        $start = Carbon::parse($timeInfo['range'][0]);

        return $start->diffInHours(Carbon::now()) > 1;
    }

    public function tags(LocalBusiness $restaurant): array
    {
        return $this->restaurantDecorator->getTags($restaurant);
    }

    public function badges(LocalBusiness $restaurant): array
    {
        return $this->restaurantDecorator->getBadges($restaurant);
    }

    public function resolveMenu(LocalBusiness $restaurant): ?Taxon
    {
        if ($this->businessContext->isActive() && $businessAccount = $this->businessContext->getBusinessAccount()) {
            $restaurantGroup = $businessAccount->getBusinessRestaurantGroup();
            $qb = $this->entityManager->getRepository(Taxon::class)->createQueryBuilder('m');
            $qb->join(BusinessRestaurantGroupRestaurantMenu::class, 'rm', Join::WITH, 'rm.menu = m.id');
            $qb->andWhere('rm.businessRestaurantGroup = :group');
            $qb->andWhere('rm.restaurant = :restaurant');
            $qb->setParameter('group', $restaurantGroup);
            $qb->setParameter('restaurant', $restaurant);

            $menu = $qb->getQuery()->getOneOrNullResult();

            if ($menu) {
                return $menu;
            }
        }

        return $restaurant->getMenuTaxon();
    }

    public function resolvePhoneNumber(OrderInterface $order): ?PhoneNumber
    {
        if (!$order->isMultiVendor()) {

            $vendor = $order->getVendor();

            if (is_callable([ $vendor, 'getTelephone' ])) {
                return $vendor->getTelephone();
            }
        }

        return $this->settingsManager->get('phone_number');
    }

    public function openingHours(LocalBusiness $restaurant, $fulfillment = 'delivery'): array
    {
        if ($this->businessContext->isActive() && $businessAccount = $this->businessContext->getBusinessAccount()) {
            if ($businessAccount->getBusinessRestaurantGroup()->hasRestaurant($restaurant)) {
                return $businessAccount->getBusinessRestaurantGroup()->getOpeningHours($fulfillment);
            }
        }

        return $restaurant->getOpeningHours($fulfillment);
    }
}
