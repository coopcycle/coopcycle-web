<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Api\Resource\TaxRate;
use AppBundle\Entity\Sylius\TaxRate as BaseTaxRate;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Doctrine\ORM\EntityManagerInterface;

final class TaxRateCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(
        private TaxesHelper $taxesHelper,
        private EntityManagerInterface $entityManager)
    {}

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return TaxRate::class === $resourceClass;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $taxRates = $this->taxesHelper->getBaseRates();
        foreach ($taxRates as $taxRate) {
            $alternativeRates = $this->taxesHelper->getAlternativeTaxRateCodes($taxRate->getCode());
            yield new TaxRate($taxRate, $this->taxesHelper->translate($taxRate->getCode()), $alternativeRates);
        }

        $serviceTaxRateCode = $this->taxesHelper->getServiceTaxRateCode();

        if (null !== $serviceTaxRateCode) {
            $serviceTaxRate = $this->entityManager->getRepository(BaseTaxRate::class)->findOneByCode($serviceTaxRateCode);
            yield new TaxRate($serviceTaxRate, $this->taxesHelper->translate($serviceTaxRate->getCode()));
        }
    }
}
