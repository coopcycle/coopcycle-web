<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class LocalBusinessRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        TranslatorInterface $translator,
        SerializerInterface $serializer,
        LocalBusinessRepository $repository,
        CacheInterface $projectCache)
    {
        $this->translator = $translator;
        $this->serializer = $serializer;
        $this->repository = $repository;
        $this->projectCache = $projectCache;
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
}
