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
     * Find the Zelty-imported products of a restaurant that the given catalog
     * no longer contains. Used to detect products removed from a catalog push.
     *
     * A restaurant can have several Zelty catalogs imported, so this stays
     * within the one being imported: another catalog's products are not
     * "removed", they simply live elsewhere. Products imported before catalog
     * ownership was recorded have no catalog id yet and are still considered,
     * exactly as they were when a restaurant could only hold one catalog —
     * re-importing their own catalog stamps them and settles the question.
     *
     * @param string[] $importedCodes
     * @return Product[]
     */
    public function findZeltyProductsForRestaurantNotIn(LocalBusiness $restaurant, string $catalogId, array $importedCodes): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.restaurant = :restaurant')
            ->andWhere("JSON_GET_FIELD_AS_TEXT(p.metadata, 'zelty_id') IS NOT NULL")
            ->andWhere("(JSON_GET_FIELD_AS_TEXT(p.metadata, 'zelty_catalog_id') IS NULL OR JSON_GET_FIELD_AS_TEXT(p.metadata, 'zelty_catalog_id') = :catalogId)")
            ->setParameter('restaurant', $restaurant)
            ->setParameter('catalogId', $catalogId);

        if (!empty($importedCodes)) {
            $qb->andWhere('p.code NOT IN (:codes)')
               ->setParameter('codes', $importedCodes);
        }

        return $qb->getQuery()->getResult();
    }
}
