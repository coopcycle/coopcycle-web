<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Sylius\Bundle\ProductBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;

class ProductRepository extends BaseProductRepository
{

    public function findByOption(ProductOptionInterface $productOption)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->innerJoin('p.options', 'o');
        $qb->where('o.option = :option');
        $qb->setParameter('option', $productOption);

        return $qb->getQuery()->getResult();
    }

    public function findOnDemandDeliveryProduct(): Product
    {
        return $this->findOneBy(['code' => 'CPCCL-ODDLVR']);
    }

    public function findByZeltyItemId(string $id): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.code = :id OR JSON_GET_FIELD_AS_TEXT(p.metadata, \'zelty_internal_id\') = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all Zelty-imported products for a restaurant whose codes are NOT in the given set.
     * Used to detect products removed from a catalog push.
     *
     * @param string[] $importedCodes
     * @return Product[]
     */
    public function findZeltyProductsForRestaurantNotIn(LocalBusiness $restaurant, array $importedCodes): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.restaurant = :restaurant')
            ->andWhere("JSON_GET_FIELD_AS_TEXT(p.metadata, 'zelty_id') IS NOT NULL")
            ->setParameter('restaurant', $restaurant);

        if (!empty($importedCodes)) {
            $qb->andWhere('p.code NOT IN (:codes)')
               ->setParameter('codes', $importedCodes);
        }

        return $qb->getQuery()->getResult();
    }
}
