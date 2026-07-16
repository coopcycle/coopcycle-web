<?php

namespace AppBundle\Twig\Components;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Business\Context as BusinessContext;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\RestaurantOrderStatsSorter;
use AppBundle\Utils\SortableRestaurantIterator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[HideSoftDeleted]
abstract class ShopCollection
{
    const EXPIRES_AFTER = 300;

    // Sort modes that rely on batched order-history queries, as opposed to
    // the default (null) time-slot sort.
    private const ORDER_STATS_SORT_MODES = ['historical_order_volume', 'ordering_potential', 'popularity'];

    public ?string $sort = null;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected LocalBusinessRepository $repository,
        protected TimingRegistry $timingRegistry,
        protected RestaurantOrderStatsSorter $restaurantOrderStatsSorter,
        protected TranslatorInterface $translator,
        protected UrlGeneratorInterface $urlGenerator,
        protected CacheInterface $appCache,
        protected Security $security,
        protected BusinessContext $businessContext)
    {}

    abstract public function getUrl(): string;

    abstract public function getTitle(): string;

    abstract protected function doGetShops(): array;

    /**
     * Sorts the given shops using the algorithm selected via $this->sort,
     * falling back to the default time-slot sort when null/unrecognized.
     *
     * @param LocalBusiness[] $shops
     * @return LocalBusiness[]
     */
    protected function sortShops(array $shops): array
    {
        if (count($shops) === 0) {
            return [];
        }

        if (in_array($this->sort, self::ORDER_STATS_SORT_MODES, true)) {
            return $this->restaurantOrderStatsSorter->sort($shops, $this->sort);
        }

        return iterator_to_array(new SortableRestaurantIterator($shops, $this->timingRegistry));
    }

    public function getCacheKey(): string
    {
        $parts = [
            'homepage',
            'collection',
            strtolower(substr(strrchr(get_class($this), '\\'), 1)),
        ];

        $parts = array_merge($parts, $this->getCacheKeyParts());

        $parts[] = $this->sort ?? 'default';

        $user = $this->security->getUser();
        if (null !== $user && $user->hasBusinessAccount() && $this->businessContext->isActive()) {
            $parts[] = 'business';
        }

        return implode('.', $parts);
    }

    protected function getCacheKeyParts(): array
    {
        return [];
    }

    public function getShops(): array
    {
        $itemsIds = $this->appCache->get($this->getCacheKey(), function (ItemInterface $item) {
            $item->expiresAfter(self::EXPIRES_AFTER);

            return array_map(fn(LocalBusiness $lb) => $lb->getId(), $this->doGetShops());
        });

        if (count($itemsIds) === 0) {
            return [];
        }

        $qb = $this->repository->createQueryBuilder('r')
            ->andWhere('r.id IN (:ids)')
            ->setParameter('ids', $itemsIds);

        $shopsById = [];
        foreach ($qb->getQuery()->getResult() as $shop) {
            $shopsById[$shop->getId()] = $shop;
        }

        // "WHERE id IN (...)" does not preserve the order of $itemsIds,
        // so we need to reorder the results to match the order computed by doGetShops()
        return array_values(array_filter(array_map(
            fn($id) => $shopsById[$id] ?? null,
            $itemsIds
        )));
    }
}
