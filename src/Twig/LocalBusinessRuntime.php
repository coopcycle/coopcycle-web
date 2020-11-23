<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
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
        CacheInterface $appCache)
    {
        $this->translator = $translator;
        $this->serializer = $serializer;
        $this->repository = $repository;
        $this->appCache = $appCache;
    }

    public function type(LocalBusiness $entity): ?string
    {
        if (Store::isValid($entity->getType())) {
            foreach (Store::values() as $value) {
                if ($value->getValue() === $entity->getType()) {

                    return $this->translator->trans(sprintf('store.%s', $value->getKey()));
                }
            }
        }

        foreach (FoodEstablishment::values() as $value) {
            if ($value->getValue() === $entity->getType()) {

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

    public function delayForHumans(LocalBusiness $restaurant, $locale): string
    {
        if ($restaurant->isFulfillmentMethodEnabled('delivery')
        &&  $restaurant->getFulfillmentMethod('delivery')->getOrderingDelayMinutes() > 0) {

            Carbon::setLocale($locale);

            $now = Carbon::now();
            $future = clone $now;
            $future->addMinutes($restaurant->getFulfillmentMethod('delivery')->getOrderingDelayMinutes());

            return $now->diffForHumans($future, ['syntax' => CarbonInterface::DIFF_ABSOLUTE]);
        }

        return '';
    }

    public function restaurantsSuggestions(): array
    {
        return $this->appCache->get('restaurant.suggestions', function (ItemInterface $item) {

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
}
