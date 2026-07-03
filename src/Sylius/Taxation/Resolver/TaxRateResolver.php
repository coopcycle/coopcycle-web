<?php

namespace AppBundle\Sylius\Taxation\Resolver;

use AppBundle\Entity\Sylius\TaxRate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolver as BaseTaxRateResolver;

class TaxRateResolver extends BaseTaxRateResolver implements TaxRateResolverInterface
{
    private $resolveAllCache = [];
    private $resolveCache = [];

    public function __construct(RepositoryInterface $taxRateRepository, private string $region)
    {
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

        $cacheKey = sprintf('%s-%s', $category->getCode(), http_build_query($criteria));

        if (!isset($this->resolveCache[$cacheKey])) {
            $this->resolveCache[$cacheKey] = parent::resolve($taxable, $criteria);
        }

        return $this->resolveCache[$cacheKey];
    }

    /**
     * {@inheritdoc}
     */
    public function resolveAll(TaxableInterface $taxable, array $criteria = []): Collection
    {
        if (null === $category = $taxable->getTaxCategory()) {

            return new ArrayCollection();
        }

        $cacheKey = sprintf('%s-%s-%s', $category->getCode(), $this->region, http_build_query($criteria));

        if (!isset($this->resolveAllCache[$cacheKey])) {

            if (count($category->getRates()) === 1) {

                if ($rate = $category->getRates()->first()) {
                    if ($rate instanceof TaxRate && $rate->getCountry() === null) {

                        $this->resolveAllCache[$cacheKey] = new ArrayCollection([$rate]);

                        return $this->resolveAllCache[$cacheKey];
                    }
                }
            }

            $criteria = array_merge(['category' => $category], $criteria);

            $matches = $this->taxRateRepository->findBy(
                array_merge(['country' => strtolower($this->region)], $criteria)
            );

            $this->resolveAllCache[$cacheKey] = new ArrayCollection($matches);
        }

        return $this->resolveAllCache[$cacheKey];
    }
}
