<?php

namespace AppBundle\Twig\Components;

use AppBundle\Annotation\HideDisabled;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Doctrine\EntityPreloader\LocalBusinessPreloader;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\LocalBusiness\Collection as ShopCollection;
use AppBundle\Entity\UI\HomepageBlock;
use AppBundle\Business\Context as BusinessContext;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\RestaurantFilter;
use AppBundle\Utils\SortableRestaurantIterator;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use League\Geotools\Geotools;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * https://ux.symfony.com/demos/live-component/infinite-scroll
 */
#[AsLiveComponent]
#[HideSoftDeleted]
#[HideDisabled(classes: [LocalBusiness::class])]
class ShopSearch
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public array $cuisine = [];

    #[LiveProp(writable: true, url: true)]
    public string $category = '';

    #[LiveProp(writable: true, url: true)]
    public string $type = '';

    #[LiveProp(writable: true, url: true)]
    public string $collection = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    public int $pageCount;

    /**
     * @var string|null
     */
    #[LiveProp(url: true)]
    public ?string $geohash = null;

    /**
     * @var string|null
     */
    #[LiveProp(url: true)]
    public ?string $address = null;

    public function __construct(
        private LocalBusinessRepository $shopRepository,
        private BusinessContext $businessContext,
        private RestaurantFilter $restaurantFilter,
        private CacheInterface $appCache,
        private TimingRegistry $timingRegistry,
        private PaginatorInterface $paginator,
        private LocalBusinessPreloader $preloader,
        private EntityManagerInterface $entityManager,
        private SettingsManager $settingsManager,
        private TranslatorInterface $translator)
    {
    }

    #[LiveAction]
    public function more(): void
    {
        ++$this->page;
    }

    public function hasMore(): bool
    {
        return $this->pageCount > $this->page;
    }

    /**
     * The categories shown in the filters bar.
     *
     * When the homepage has been customized with the homepage editor, its shop
     * collections drive the filters bar, so that both pages share the same structure.
     * Otherwise, a default set of categories is used.
     */
    public function getCategories(): array
    {
        $categories = $this->getHomepageCategories();

        return count($categories) > 0 ? $categories : $this->getDefaultCategories();
    }

    private function getHomepageCategories(): array
    {
        if (!$this->settingsManager->getBoolean('homepage_published')) {
            return [];
        }

        $blocks = $this->entityManager->getRepository(HomepageBlock::class)
            ->findBy([], ['position' => 'ASC']);

        $categories = [];

        foreach ($blocks as $block) {

            if ('shop_collection' !== $block->type) {
                continue;
            }

            $component = $block->data['component'] ?? null;
            // An empty args object round-trips through PHP as a JSON array
            $args = is_array($block->data['args'] ?? null) ? $block->data['args'] : [];

            $category = match ($component) {
                'newest' => $this->makeCategory('category', 'new', 'homepage.shops.new'),
                'featured' => $this->makeCategory('category', 'featured', 'homepage.featured'),
                'exclusive' => $this->makeCategory('category', 'exclusive', 'homepage.exclusive'),
                // The homepage banner-like wording is too long for a filter chip
                'zerowaste' => $this->makeCategory('category', 'zerowaste', 'restaurant.list.tags.zerowaste'),
                'edenred' => $this->makeCategory('category', 'edenred', 'homepage.edenred'),
                'cuisine' => isset($args['cuisine']) ?
                    $this->makeCategory('cuisine', $args['cuisine'], $args['cuisine'], 'cuisines') : null,
                'type' => isset($args['type']) ? $this->makeTypeCategory($args['type']) : null,
                'custom' => isset($args['slug']) ? $this->makeCollectionCategory($args['slug']) : null,
                default => null,
            };

            if (null !== $category) {
                $categories[] = $category;
            }
        }

        // The same collection may be used by several blocks (with different sort options)
        return array_values(array_column($categories, null, 'id'));
    }

    private function getDefaultCategories(): array
    {
        $categories = [];

        $types = $this->getTypes();
        if (count($types) > 1) {
            foreach ($types as $type) {
                if ($category = $this->makeTypeCategory(LocalBusiness::getKeyForType($type))) {
                    $categories[] = $category;
                }
            }
        }

        $categories[] = $this->makeCategory('category', 'new', 'homepage.shops.new');

        if ($this->shopRepository->countZeroWaste() > 0) {
            $categories[] = $this->makeCategory('category', 'zerowaste', 'restaurant.list.tags.zerowaste');
        }

        if ($this->shopRepository->countFeatured() > 0) {
            $categories[] = $this->makeCategory('category', 'featured', 'homepage.featured');
        }

        if ($this->shopRepository->countExclusive() > 0) {
            $categories[] = $this->makeCategory('category', 'exclusive', 'homepage.exclusive');
        }

        return $categories;
    }

    private function makeTypeCategory(string $typeKey): ?array
    {
        $type = LocalBusiness::getTypeForKey($typeKey);

        if (null === $type) {
            return null;
        }

        return $this->makeCategory('type', $typeKey, LocalBusiness::getTransKeyForType($type));
    }

    private function makeCollectionCategory(string $slug): ?array
    {
        $collection = $this->entityManager->getRepository(ShopCollection::class)
            ->findOneBy(['slug' => $slug]);

        if (null === $collection) {
            return null;
        }

        return [
            'id' => sprintf('collection|%s', $slug),
            'filter' => 'collection',
            'value' => $slug,
            'label' => $collection->getTitle(),
            'active' => $this->collection === $slug,
        ];
    }

    private function makeCategory(string $filter, string $value, string $transKey, ?string $domain = null): array
    {
        return [
            'id' => sprintf('%s|%s', $filter, $value),
            'filter' => $filter,
            'value' => $value,
            'label' => $this->translator->trans($transKey, [], $domain),
            'active' => $this->isActiveFilter($filter, $value),
        ];
    }

    private function isActiveFilter(string $filter, string $value): bool
    {
        return match ($filter) {
            'category' => $this->category === $value,
            'type' => $this->type === $value,
            'cuisine' => [$value] === array_values($this->cuisine),
            'collection' => $this->collection === $value,
            default => false,
        };
    }

    public function hasFilters(): bool
    {
        return count($this->getFilters()) > 0;
    }

    public function getTypes(): array
    {
        return array_keys($this->shopRepository->countByType());
    }

    public function getCuisines(): array
    {
        return $this->shopRepository->findCuisinesByFilters($this->getFilters());
    }

    // FIXME Should be "predictable", i.e alway in same order
    public function getFilters(): array
    {
        $filters = [];

        if (!empty($this->cuisine)) {
            $filters['cuisine'] = $this->cuisine;
        }

        if (!empty($this->category)) {
            $filters['category'] = $this->category;
        }

        if (!empty($this->type)) {
            if ($typeForKey = LocalBusiness::getTypeForKey($this->type)) {
                $filters['type'] = $typeForKey;
            }
        }

        if (!empty($this->collection)) {
            $filters['collection'] = $this->collection;
        }

        return $filters;
    }

    public function getShops(): PaginationInterface
    {
        $restaurantsIds = $this->appCache->get($this->getCacheKey(), function (ItemInterface $item) {

            $item->expiresAfter(60 * 5);

            return array_map(fn (LocalBusiness $s) => $s->getId(), $this->shopRepository->findByFilters($this->getFilters()));
        });

        $matches = [];

        if (count($restaurantsIds) > 0) {

            $qb = $this->shopRepository->createQueryBuilder('r');
            $qb->add('where', $qb->expr()->in('r.id', $restaurantsIds));

            $matches = $qb->getQuery()->getResult();

            // Preload entities to optimize N+1 queries
            $this->preloader->preload($matches);
        }

        if (!empty($this->geohash) || !empty($this->address)) {

            // $matches = $qb->getQuery()->getResult();

            $geohash = null;

            if (!empty($this->geohash)) {
                $geohash = $this->geohash;
            } else if (!empty($this->address)) {
                $geohash = $this->getAddressGeohash($this->address) ?? null;
            }

            if (null !== $geohash) {

                $geotools = new Geotools();

                try {

                    $decoded = $geotools->geohash()->decode($geohash);

                    $latitude = $decoded->getCoordinate()->getLatitude();
                    $longitude = $decoded->getCoordinate()->getLongitude();

                    $matches = $this->restaurantFilter->matchingLatLng($matches, $latitude, $longitude);

                } catch (\InvalidArgumentException|\RuntimeException $e) {
                    // Some funny guys may have tried a SQL injection
                }
            }
        }

        $iterator = new SortableRestaurantIterator($matches, $this->timingRegistry);
        $matches = iterator_to_array($iterator);

        $shops = $this->paginator->paginate(
            $matches,
            $this->page,
            12
        );

        $this->pageCount = (int) \ceil($shops->getTotalItemCount() / $shops->getItemNumberPerPage());

        return $shops;
    }

    #[LiveAction]
    public function toggleCuisine(#[LiveArg] string $name)
    {
        if (in_array($name, $this->cuisine)) {
            $this->cuisine = array_filter($this->cuisine, fn ($c) => $c !== $name);
        } else {
            $this->cuisine[] = $name;
        }
        $this->page = 1;
    }

    #[LiveAction]
    public function filterBy(#[LiveArg] string $filter, #[LiveArg] string $value)
    {
        $this->category = 'category' === $filter ? $value : '';
        $this->type = 'type' === $filter ? $value : '';
        $this->collection = 'collection' === $filter ? $value : '';
        $this->cuisine = 'cuisine' === $filter ? [$value] : [];
        $this->page = 1;
    }

    /**
     * The cache key is built with all query params alphabetically sorted.
     * With this function we make sure that same filters in different order represent the same cache key.
     */
    public function getCacheKey(): string
    {
        $query = [
            'category' => $this->category,
            'cuisine' => $this->cuisine,
            'type' => $this->type,
            'collection' => $this->collection,
        ];

        $query = array_filter($query);

        if (isset($query['cuisine'])) {
            sort($query['cuisine']);
        }

        ksort($query);

        $cacheKey = sprintf('shops.list.filters|%s', http_build_query($query));

        if ($this->businessContext->isActive()) {

            return sprintf('%s.%s', $cacheKey, '_business');
        }

        return $cacheKey;
    }

    private function getAddressGeohash(string $address): ?string
    {
        $data = json_decode(urldecode(base64_decode($address)), true);

        return $data['geohash'] ?? null;
    }
}
