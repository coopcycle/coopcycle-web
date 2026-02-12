<?php

namespace AppBundle\Entity;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use AppBundle\Entity\Cuisine;
use AppBundle\Entity\LocalBusiness\Collection as ShopCollection;
use AppBundle\Entity\LocalBusiness\CollectionItem as ShopCollectionItem;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductImage;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptions;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Utils\RestaurantFilter;
use Carbon\Carbon;
use DeepCopy\Filter\KeepFilter;
use DeepCopy\Filter\ReplaceFilter;
use DeepCopy\Filter\SetNullFilter;
use DeepCopy\Matcher\PropertyMatcher;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Resource\Model\AbstractTranslation;
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

    public function findLatest($limit = self::LATESTS_SHOPS_LIMIT)
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
        $qb = $this->createQueryBuilder('r');
        $qb
            ->innerJoin('r.servesCuisine', 'c')
            ->select('c.name')
            ->addSelect('COUNT(r.id) AS cnt');

        $this->addBusinessContextClause($qb, 'r');

        $qb
            ->groupBy('c.name')
            ->orderBy('cnt', 'DESC');

        $result = $qb->getQuery()->getArrayResult();

        return array_combine(
            array_map(fn ($res) => $res['name'], $result),
            array_map(fn ($res) => $res['cnt'], $result)
        );
    }

    public function findByCuisine(string $cuisine)
    {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.servesCuisine', 'c')
            ->andWhere('c.name = :cuisine')
            ->setParameter('cuisine', $cuisine);

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getResult();
    }

    public function findCuisinesByFilters(array $filters = [])
    {
        if (empty($filters)) {

            $names = array_keys($this->countByCuisine());

            if (count($names) === 0) {
                return [];
            }

            $qb = $this->getEntityManager()->getRepository(Cuisine::class)
                ->createQueryBuilder('c')
                ->andWhere('c.name IN (:cuisines)')
                ->setParameter('cuisines', $names);

            return $qb->getQuery()->getResult();
        }

        unset($filters['cuisine']);

        $subquery = $this->findByFilters($filters, true);
        $subquery->select('r.id');

        $qb = $this->getEntityManager()->getRepository(Cuisine::class)
                ->createQueryBuilder('c');
        $qb->innerJoin('c.restaurants', 'rc', Expr\Join::WITH, $qb->expr()->in('rc.id', $subquery->getDQL()));
        $qb->setParameters($subquery->getQuery()->getParameters());


        return $qb->getQuery()->getResult();
    }

    public function findByFilters(array $filters, bool $asQueryBuilder = false)
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
                            ->innerJoin('r.servesCuisine', 'c', Expr\Join::WITH, $qb->expr()->in('c.name', ':cuisines'))
                            ->setParameter('cuisines', $value);
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
                    case 'collection':
                        $qb
                            ->innerJoin(ShopCollectionItem::class, 'collection_item', Expr\Join::WITH, 'collection_item.shop = r.id')
                            ->innerJoin(ShopCollection::class, 'collection', Expr\Join::WITH, 'collection_item.collection = collection.id')
                            ->andWhere('collection.slug = :slug')
                            ->setParameter('slug', $value);
                        break;
                    default:
                        break;
                }
            }
        }

        return $asQueryBuilder ? $qb : $qb->getQuery()->getResult();
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

    public function findOthers(LocalBusiness $restaurant)
    {
        $qb = $this->createQueryBuilder('r');

        $qb->select('r.id')->addSelect('r.name');
        $qb->andWhere('r.id != :restaurant_id');
        $qb->setParameter('restaurant_id', $restaurant);

        $qb
            ->orderBy('r.name', 'ASC');

        return $qb->getQuery()->getArrayResult();
    }

    public function copyProducts(LocalBusiness $src, LocalBusiness $dest)
    {
        if ($src === $dest) {
            throw new \Exception('Source and destination are the same');
        }

        $copier = new \DeepCopy\DeepCopy();
        $copier->addFilter(new \DeepCopy\Filter\ChainableFilter(
            new \DeepCopy\Filter\Doctrine\DoctrineProxyFilter()),
            new \DeepCopy\Matcher\Doctrine\DoctrineProxyMatcher()
        );
        $copier->addFilter(new \DeepCopy\Filter\Doctrine\DoctrineCollectionFilter(), new \DeepCopy\Matcher\PropertyTypeMatcher('Doctrine\Common\Collections\Collection'));

        // Set "id" to NULL so that new entities are created
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(Product::class, 'id'));
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(ProductAttributeValue::class, 'id'));
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(ProductOption::class, 'id'));
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(ProductOptions::class, 'id'));
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(ProductOptionValue::class, 'id'));
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(ProductVariant::class, 'id'));
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(AbstractTranslation::class, 'id'));

        // FIXME
        // If original image is removed, both images will be removed
        // We should copy also the file
        $copier->addFilter(new SetNullFilter(), new PropertyMatcher(ProductImage::class, 'id'));

        $generateUUID = function ($currentValue) {
            return Uuid::uuid4()->toString();
        };

        $replaceRestaurant = function ($currentValue) use ($dest) {
            return $dest;
        };

        // Generate new UUIDs for "code"
        $copier->addFilter(new ReplaceFilter($generateUUID), new PropertyMatcher(Product::class, 'code'));
        $copier->addFilter(new ReplaceFilter($generateUUID), new PropertyMatcher(ProductOption::class, 'code'));
        $copier->addFilter(new ReplaceFilter($generateUUID), new PropertyMatcher(ProductOptionValue::class, 'code'));
        $copier->addFilter(new ReplaceFilter($generateUUID), new PropertyMatcher(ProductVariant::class, 'code'));

        // Replace restaurant to dest
        $copier->addFilter(new ReplaceFilter($replaceRestaurant), new PropertyMatcher(Product::class, 'restaurant'));
        $copier->addFilter(new ReplaceFilter($replaceRestaurant), new PropertyMatcher(ProductOption::class, 'restaurant'));

        // Keep configured tax category
        $copier->addFilter(new KeepFilter(), new PropertyMatcher(ProductVariant::class, 'taxCategory'));

        $productOptions = [];
        foreach ($src->getProductOptions() as $productOption) {

            $copy = $copier->copy($productOption);

            $productOptions[$productOption->getCode()] = $copy;

            $this->getEntityManager()->persist($copy);
        }

        foreach ($src->getProducts() as $product) {

            $copy = $copier->copy($product);

            $copy->setSlug($copy->getCode());

            // Avoid duplicating options
            if (count($product->getProductOptions()) > 0) {
                $copy->getProductOptions()->clear();
                foreach ($product->getOptions() as $option) {
                    $optionCopy = $productOptions[$option->getCode()];
                    $copy->addOption($optionCopy);
                }
            }

            // Keep only the "default" variant
            $defaultVariant = $copy->getVariants()->first();
            $copy->getVariants()->clear();
            $copy->addVariant($defaultVariant);

            // Ignore reusable packagings
            $product->setReusablePackagingEnabled(false);
            $product->clearReusablePackagings();

            // Re-set original attribute, because translations are without ID
            $srcAttributes = array_map(fn ($attr) => $attr->getAttribute(), $product->getAttributes()->toArray());
            foreach ($copy->getAttributes() as $attributeValue) {
                foreach ($srcAttributes as $srcAttribute) {
                    if ($attributeValue->getAttribute()->getId() === $srcAttribute->getId()) {
                        $attributeValue->setAttribute($srcAttribute);
                    }
                }
            }

            $this->getEntityManager()->persist($copy);
        }

        $this->getEntityManager()->flush();
    }

    public function countFeatured(): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.featured = :featured')
            ->setParameter('featured', true);

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countExclusive(): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.exclusive = :exclusive')
            ->setParameter('exclusive', true);

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findNew($since = '3 months')
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.createdAt > :since')
            ->setParameter('since', date('Y-m-d', strtotime($since)));

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getResult();
    }

    public function findByCollection(string $slug)
    {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin(ShopCollectionItem::class, 'collection_item', Expr\Join::WITH, 'collection_item.shop = r.id')
            ->innerJoin(ShopCollection::class, 'collection', Expr\Join::WITH, 'collection_item.collection = collection.id')
            ->andWhere('collection.slug = :slug')
            ->setParameter('slug', $slug);

        $this->addBusinessContextClause($qb, 'r');

        return $qb->getQuery()->getResult();
    }
}
