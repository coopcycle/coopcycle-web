<?php

namespace AppBundle\Entity;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Entity\Cuisine;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Utils\RestaurantFilter;
use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class LocalBusinessRepository extends EntityRepository
{
    private $businessContext;
    private $restaurantFilter;
    private $context = FoodEstablishment::class;
    private $typeFilter = FoodEstablishment::RESTAURANT;
    const LATESTS_SHOPS_LIMIT = 12;

    public function withContext(string $context)
    {
        $repository = clone $this;

        return $repository->setContext($context);
    }

    public function setRestaurantFilter(RestaurantFilter $restaurantFilter)
    {
        $this->restaurantFilter = $restaurantFilter;

        return $this;
    }

    public function setBusinessContext(BusinessContext $businessContext)
    {
        $this->businessContext = $businessContext;

        return $this;
    }

    public function setContext(string $context)
    {
        $this->context = $context;

        return $this;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    // TODO : fix this to check that restaurants are really in delivery/radius zone
    private function createNearbyQueryBuilder($latitude, $longitude, $distance = 3500)
    {
        $qb = $this->createQueryBuilder('r');

        self::addNearbyQueryClause($qb, $latitude, $longitude, $distance);

        return $qb;
    }

    // TODO : fix this to check that restaurants are really in delivery/radius zone
    public static function addNearbyQueryClause(QueryBuilder $qb, $latitude, $longitude, $distance = 3500)
    {
        $qb->innerJoin($qb->getRootAlias() . '.address', 'a', Expr\Join::WITH);

        $geomFromText = new Expr\Func('ST_GeomFromText', array(
            $qb->expr()->literal("POINT({$longitude} {$latitude})"),
            '4326'
        ));

        $dist = new Expr\Func('ST_Distance', array(
            $geomFromText,
            'a.geo'
        ));

        // Add calculated distance field
        $qb->addSelect($dist . ' AS HIDDEN distance');

        $within = new Expr\Func('ST_DWithin', array(
            $geomFromText,
            'a.geo',
            $distance
        ));

        $qb->add('where', $qb->expr()->eq(
            $within,
            $qb->expr()->literal(true)
        ));
    }

    public function countNearby($latitude, $longitude, $distance = 5000, $limit = 10, $offset = 0)
    {
        $qb = $this->createNearbyQueryBuilder($latitude, $longitude, $distance);

        $qb->select($qb->expr()->count('r'));

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findNearby($latitude, $longitude, $distance = 5000, $limit = 10, $offset = 0)
    {
        $qb = $this->createNearbyQueryBuilder($latitude, $longitude, $distance);

        $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $qb->orderBy('distance');

        return $qb->getQuery()->getResult();
    }

    /**
     * We will obsviously have a fairly small amount of restaurants.
     * So, there is not significant performance downside in loading them all.
     * Event with 50 restaurants, it takes ~ 500ms to complete.
     */
    public function findByLatLng($latitude, $longitude)
    {
        return $this->restaurantFilter->matchingLatLng($this->findAll(), $latitude, $longitude);
    }

    public function search($q)
    {
        $qb = $this->createQueryBuilder('r');

        $qb
            ->where('LOWER(r.name) LIKE :q')
            ->setParameter('q', '%' . strtolower($q) . '%');

        return $qb->getQuery()->getResult();
    }

    public function findRandom($maxResults = 3)
    {
        // Do not use ORDER BY RAND()
        // @see https://github.com/doctrine/doctrine2/issues/5479
        $qb = $this->createQueryBuilder('r');

        $rows = $qb
            ->select('r.id')
            ->getQuery()
            ->getArrayResult();

        shuffle($rows);

        $rows = array_slice($rows, 0, $maxResults);

        $ids = array_map(function ($row) {
            return $row['id'];
        }, $rows);

        return $this->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', array_values($ids))
            ->getQuery()
            ->getResult();
    }

    public function countAll()
    {
        $qb = $this
            ->createQueryBuilder('r')
            ->select('COUNT(r)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findAllForType()
    {
        $qb = $this->createQueryBuilder('o');

        $this->addBusinessContextClause($qb, 'o');

        if (null !== $this->typeFilter) {
            $types[] = $this->typeFilter;
            $qb->add('where', $qb->expr()->in('o.type', $types));
        }

        return $qb->getQuery()->getResult();
    }

    public function findByOption(ProductOptionInterface $option)
    {
        // @see https://stackoverflow.com/questions/33346113/doctrine2-manytomany-inverse-querybuilder
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.productOptions', 'o')
            ->andWhere('o.id = :option')
            ->setParameter('option', $option)
        ;

        return $qb->getQuery()->getResult();
    }

    private function createZeroWasteQueryBuilder()
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere(
                'r.enabled = :enabled'
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('r.depositRefundEnabled', ':enabled'),
                    $qb->expr()->eq('r.loopeatEnabled', ':enabled')
                )
            )
            ->setParameter('enabled', true);

        return $qb;
    }

    public function findZeroWaste()
    {
        return $this->createZeroWasteQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    public function findFeatured()
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.featured = :featured')
            ->setParameter('featured', true);

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getResult();
    }

    public function findExclusives()
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.exclusive = :exclusive')
            ->setParameter('exclusive', true);

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getResult();
    }

    public function findLatest($limit)
    {
        $qb = $this->createQueryBuilder('r');

        $this->addBusinessContextClause($qb, 'r');

        $qb
            ->setMaxResults($limit)
            ->orderBy('r.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function countByCuisine(): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS cnt')
            ->addSelect('c.id')
            ->addSelect('c.name')
            ->innerJoin('r.servesCuisine', 'c');

        $this->addBusinessContextClause($qb, 'r');

        $qb
            ->groupBy('c.id')
            ->orderBy('cnt', 'DESC');

        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByCuisine($cuisine) {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.servesCuisine', 'c')
            ->andWhere('c.id = :cuisine_id')
            ->setParameter('cuisine_id', $cuisine);

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getResult();
    }

    public function findExistingCuisines() {
        $qb = $this->createQueryBuilder('r')
            ->select('c')
            ->from(Cuisine::class, 'c')
            ->innerJoin('c.restaurants', 'cr');

        $this->addBusinessContextClause($qb, 'r');

        $qb->orderBy('c.name');

        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByFilters($filters)
    {
        $qb = $this->createQueryBuilder('r');

        $this->addBusinessContextClause($qb, 'r');

        if (count($filters) > 0) {
            foreach ($filters as $key => $value) {
                switch($key) {
                    case 'type':
                        $qb
                            ->andWhere('r.type = :type')
                            ->setParameter('type', $value);
                        break;
                    case 'cuisine':
                        $qb
                            ->innerJoin('r.servesCuisine', 'c', 'WITH', $qb->expr()->in('c.id', ':cuisineIds'))
                            ->setParameter('cuisineIds', $value);
                        break;
                    case 'category':
                        switch($value) {
                            case 'featured':
                                $qb
                                    ->andWhere('r.featured = :featured')
                                    ->setParameter('featured', true);
                                break;
                            case 'exclusive':
                                $qb
                                    ->andWhere('r.exclusive = :exclusive')
                                    ->setParameter('exclusive', true);
                                break;
                            case 'new':
                                $qb
                                    ->setMaxResults(self::LATESTS_SHOPS_LIMIT)
                                    ->orderBy('r.createdAt', 'DESC');
                                break;
                            case 'zerowaste':
                                $qb
                                    ->andWhere(
                                        $qb->expr()->orX(
                                        $qb->expr()->eq('r.depositRefundEnabled', ':enabled'),
                                        $qb->expr()->eq('r.loopeatEnabled', ':enabled'))
                                    )
                                    ->setParameter('enabled', true);
                                break;
                            default:
                                break;
                        }
                    default:
                        break;
                }
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function countZeroWaste()
    {
        $qb = $this->createZeroWasteQueryBuilder();
        $qb
            ->select('COUNT(r.id)');

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()
            ->getSingleScalarResult();
    }

    public function countByType(): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->select('r.type')
            ->addSelect('COUNT(r.id) AS cnt');

        $this->addBusinessContextClause($qb, 'r');

        $qb
            ->groupBy('r.type')
            ->orderBy('cnt', 'DESC');

        $result = $qb->getQuery()->getArrayResult();

        return array_combine(
            array_map(fn ($res) => $res['type'], $result),
            array_map(fn ($res) => $res['cnt'], $result)
        );
    }

    public function setTypeFilter(?string $type = null)
    {
        $this->typeFilter = $type;

        return $this;
    }

    public function withTypeFilter(string $type)
    {
        $repository = clone $this;

        return $repository->setTypeFilter($type);
    }

    public function withoutTypeFilter()
    {
        $repository = clone $this;

        return $repository->setTypeFilter(null);
    }

    /**
     * @return int[]
     */
    public function findNewRestaurantIds()
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->select('r.id')
            ->setMaxResults(self::LATESTS_SHOPS_LIMIT)
            ->orderBy('r.createdAt', 'DESC');

        return array_map(fn ($result) => $result['id'], $qb->getQuery()->getArrayResult());
    }

    public function isRestaurantAvailableInBusinessAccount(LocalBusiness $restaurant)
    {
        $qb = $this->createQueryBuilder('r');

        $this->addBusinessContextClause($qb,'r');

        $qb
            ->andWhere('r.id = :restaurant')
            ->setParameter('restaurant', $restaurant);

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function addBusinessContextClause(QueryBuilder $qb, string $alias)
    {
        if (null !== $this->businessContext && $this->businessContext->isActive()) {
            $qb->innerJoin(BusinessRestaurantGroupRestaurantMenu::class, 'g', Expr\Join::WITH, sprintf('g.restaurant = %s', $alias))
                ->innerJoin(BusinessAccount::class, 'ba', Expr\Join::WITH, 'ba.businessRestaurantGroup = g.businessRestaurantGroup and ba.id = :business_account')
                ->setParameter(':business_account', $this->businessContext->getBusinessAccount());
        }
    }
}
