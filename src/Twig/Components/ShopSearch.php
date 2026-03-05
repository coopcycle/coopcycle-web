<?php

namespace AppBundle\Twig\Components;

use AppBundle\Annotation\HideDisabled;
use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Doctrine\EntityPreloader\LocalBusinessPreloader;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Business\Context as BusinessContext;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\RestaurantFilter;
use AppBundle\Utils\SortableRestaurantIterator;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use League\Geotools\Geotools;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
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
        private LocalBusinessPreloader $preloader)
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

    public function getCategories(): array
    {
        $zeroWasteCount = $this->shopRepository->countZeroWaste();
        $featuredCount = $this->shopRepository->countFeatured();
        $exclusiveCount = $this->shopRepository->countExclusive();

        $categories = [
            [ 'key' => 'new', 'transKey' => 'homepage.shops.new' ],
        ];

        if ($zeroWasteCount > 0) {
            $categories[] = [ 'key' => 'zerowaste', 'transKey' => 'restaurant.list.tags.zerowaste' ];
        }

        if ($featuredCount > 0) {
            $categories[] = [ 'key' => 'featured', 'transKey' =>'homepage.featured' ];
        }

         if ($exclusiveCount > 0) {
            $categories[] = [ 'key' => 'exclusive', 'transKey' => 'homepage.exclusive' ];
        }

        return $categories;
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
    public function setCategory(#[LiveArg] string $key)
    {
        $this->category = $key;
        $this->type = '';
        $this->page = 1;
    }

    #[LiveAction]
    public function setType(#[LiveArg] string $type)
    {
        $this->type = $type;
        $this->category = '';
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
