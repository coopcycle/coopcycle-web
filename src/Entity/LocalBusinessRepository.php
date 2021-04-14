<?php

namespace AppBundle\Entity;

use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
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
    private $restaurantFilter;
    private $context = FoodEstablishment::class;

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

    public function findAllSorted()
    {
        $qb = $this->createQueryBuilder('o');

        $r = new \ReflectionClass($this->context);
        $values = $r->getMethod('values')->invoke(null);

        $types = [];
        foreach ($values as $value) {
            $types[] = $value->getValue();
        }

        $qb->add('where', $qb->expr()->in('o.type', $types));

        $matches = $qb->getQuery()->getResult();

        // 0 - featured & opened restaurants
        // 1 - opened restaurants
        // 2 - closed restaurants
        // 3 - disabled restaurants

        $now = Carbon::now();

        $nextOpeningComparator = function (LocalBusiness $a, LocalBusiness $b) use ($now) {

            $aNextOpening = $a->getNextOpeningDate($now);
            $bNextOpening = $b->getNextOpeningDate($now);

            $compareNextOpening = $aNextOpening === $bNextOpening ?
                0 : ($aNextOpening < $bNextOpening ? -1 : 1);

            return $compareNextOpening;
        };

        usort($matches, $nextOpeningComparator);

        $featured = array_filter($matches, function (LocalBusiness $lb) use ($now) {
            return $lb->isFeatured() && $lb->isOpen($now);
        });
        $opened = array_filter($matches, function (LocalBusiness $lb) use ($now, $featured) {
            return !in_array($lb, $featured, true) && $lb->isOpen($now);
        });
        $closed = array_filter($matches, function (LocalBusiness $lb) use ($now) {
            return !$lb->isOpen($now);
        });

        return array_merge($featured, $opened, $closed);
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

    public function countZeroWaste()
    {
        $qb = $this->createZeroWasteQueryBuilder();
        $qb
            ->select('COUNT(r.id)');

        return $qb->getQuery()
            ->getSingleScalarResult();
    }
}
