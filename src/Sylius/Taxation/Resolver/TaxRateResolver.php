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
    /**
     * @param RepositoryInterface $taxRateRepository
     * @param string $region
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
        return parent::resolve($taxable, $criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveAll(TaxableInterface $taxable, array $criteria = []): Collection
    {
        if (null === $category = $taxable->getTaxCategory()) {

            return new ArrayCollection();
        }

        if (count($category->getRates()) === 1) {
            if ($rate = $category->getRates()->first()) {
                if ($rate instanceof TaxRate && $rate->getCountry() === null) {

                    return new ArrayCollection([$rate]);
                }
            }
        }

        $criteria = array_merge(['category' => $category], $criteria);

        $matches = $this->taxRateRepository->findBy(
            array_merge(['country' => strtolower($this->region)], $criteria)
        );

        return new ArrayCollection($matches);
    }
}
