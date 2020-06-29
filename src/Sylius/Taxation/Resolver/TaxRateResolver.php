<?php

namespace AppBundle\Sylius\Taxation\Resolver;

use AppBundle\Entity\Sylius\TaxRate;
use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolver as BaseTaxRateResolver;

class TaxRateResolver extends BaseTaxRateResolver
{
    private $region;

    /**
     * @var RepositoryInterface $taxRateRepository
     * @var string $region
     */
    public function __construct(RepositoryInterface $taxRateRepository, string $region)
    {
        $this->region = $region;

        parent::__construct($taxRateRepository);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(TaxableInterface $taxable, array $criteria = []): ?TaxRateInterface
    {
        if (null === $category = $taxable->getTaxCategory()) {
            return null;
        }

        // Legacy tax rates
        if (count($category->getRates()) === 1) {
            if ($rate = $category->getRates()->first()) {
                if ($rate instanceof TaxRate && $rate->getCountry() === null) {
                    return $rate;
                }
            }
        }

        $qb = $this->taxRateRepository->createQueryBuilder('r');

        $qb->andWhere('r.category = :category');
        $qb->setParameter('category', $category);

        // Make sure to override country
        if (isset($criteria['country'])) {
            unset($criteria['country']);
        }
        $qb->andWhere('r.country = :country');
        $qb->setParameter('country', $this->region);

        foreach ($criteria as $property => $value) {
            $qb->andWhere(sprintf('r.%s = :%s', $property, $property));
            $qb->setParameter($property, $value);
        }

        // Make sure there will be one result,
        // or getOneOrNullResult will throw an Exception
        $qb->setFirstResult(0);
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
