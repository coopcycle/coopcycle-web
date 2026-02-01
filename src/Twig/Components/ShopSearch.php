<?php

namespace AppBundle\Twig\Components;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Business\Context as BusinessContext;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
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
class ShopSearch
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public array $cuisine = [];

    #[LiveProp(writable: true, url: true)]
    public string $category = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    public int $pageCount;

    public function __construct(
        private LocalBusinessRepository $shopRepository,
        private BusinessContext $businessContext,
        private PaginatorInterface $paginator)
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
        return [
          [ 'key' => 'featured', 'transKey' =>'homepage.featured' ],
          [ 'key' => 'exclusive', 'transKey' => 'homepage.exclusive' ],
          [ 'key' => 'new', 'transKey' => 'homepage.shops.new' ],
          [ 'key' => 'zerowaste', 'transKey' => 'restaurant.list.tags.zerowaste' ],
        ];
    }

    public function getCuisines(): array
    {
        return $this->shopRepository->findExistingCuisines();
    }

    // FIXME Should be "predictable", i.e alway in same order
    public function getFilters(): array
    {
        $filters = [];

        if (!empty($this->cuisine)) {
            $filters['cuisine'] = $this->cuisine;
        }

        if (!empty($this->category)) {
            $typeForKey = LocalBusiness::getTypeForKey($this->category);
            $filters['category'] = $typeForKey ?? $this->category;
        }

        return $filters;
    }

    public function getFiltersKey(): string
    {
        return json_encode($this->getFilters());
    }

    public function getShops(): PaginationInterface
    {
        $filters = $this->getFilters();

        // $this->shopRepository->setBusinessContext($this->businessContext);

        $qb = $this->shopRepository->findByFilters($filters, true);

        $shops = $this->paginator->paginate(
            $qb,
            $this->page,
            15,
            [
                // PaginatorInterface::DISTINCT => false,
            ]
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

        // $this->page = 1;

        // $this->emit('filtersChanged');
    }

    #[LiveAction]
    public function setCategory(#[LiveArg] string $key)
    {
        $this->category = $key;

        // $this->page = 1;
        // $this->cuisine = [];

        // $this->emit('filtersChanged');
    }

    #[LiveListener('filtersChanged')]
    public function resetPage()
    {
        $this->page = 1;
    }
}
