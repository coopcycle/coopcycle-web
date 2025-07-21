<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\TaxRate;
use AppBundle\Entity\Sylius\TaxRate as BaseTaxRate;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Doctrine\ORM\EntityManagerInterface;

final class TaxRateProvider implements ProviderInterface
{
    public function __construct(
        private TaxesHelper $taxesHelper,
        private EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
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
