<?php

namespace AppBundle\Sylius\Taxation;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

class TaxesProvider
{
    public function __construct(
        private readonly FactoryInterface $taxCategoryFactory,
        private readonly FactoryInterface $taxRateFactory)
    {
    }

    public function getCategories()
    {
        $path = realpath(__DIR__ . '/../../Resources/config/taxation.yml');
        $parser = new YamlParser();
        $config = $parser->parseFile($path, Yaml::PARSE_CONSTANT);

        $categories = [];

        foreach ($config as $categoryCode => $data) {

            $taxCategory = $this->taxCategoryFactory->createNew();
            $taxCategory->setCode(strtoupper($categoryCode));
            $taxCategory->setName(sprintf('tax_category.%s', $categoryCode));

            foreach ($data['rates'] as $region => $rates) {
                foreach ($rates as $rateCode => $amount) {

                    $taxRate = $this->taxRateFactory->createNew();

                    $taxRate->setCode(strtoupper(sprintf('%s_%s_%s', str_replace('-', '_', $region), $categoryCode, $rateCode)));
                    $taxRate->setName(sprintf('tax_rate.%s', $rateCode));
                    $taxRate->setCalculator('default');
                    $taxRate->setIncludedInPrice($this->isIncludedInPrice($region));
                    $taxRate->setAmount($amount);

                    $taxRate->setCountry($region);

                    $taxCategory->addRate($taxRate);
                }
            }

            $categories[] = $taxCategory;
        }

        return $categories;
    }

    private function isIncludedInPrice($region)
    {
        if ($region === 'ca-bc') {

            return false;
        }

        return true;
    }

    public function synchronize(TaxCategory $expected, TaxCategory $actual, ?LoggerInterface $logger = null)
    {
        $logger = $logger ?? new NullLogger();

        $migrations = [];

        if ($expected->getCode() !== $actual->getCode()) {
            $logger->info(sprintf('Changing tax category code from « %s » to « %s »', $actual->getCode(), $expected->getCode()));
            $actual->setCode($expected->getCode());
        }

        if ($expected->getName() !== $actual->getName()) {
            $logger->info(sprintf('Changing tax category name from « %s » to « %s »', $actual->getName(), $expected->getName()));
            $actual->setName($expected->getName());
        }

        foreach ($expected->getRates() as $expectedTaxRate) {
            if ($match = $this->lookupTaxRate($actual, $expectedTaxRate)) {
                if ($match->getCode() !== $expectedTaxRate->getCode()) {
                    $logger->info(sprintf('Changing tax rate code from « %s » to « %s »', $match->getCode(), $expectedTaxRate->getCode()));
                    $migrations[] = [ $match->getCode(), $expectedTaxRate->getCode() ];
                    $match->setCode($expectedTaxRate->getCode());
                }
                if ($match->getName() !== $expectedTaxRate->getName()) {
                    $logger->info(sprintf('Changing tax rate name from « %s » to « %s »', $match->getName(), $expectedTaxRate->getName()));
                    $match->setName($expectedTaxRate->getName());
                }
                if ($match->isIncludedInPrice() !== $expectedTaxRate->isIncludedInPrice()) {
                    $logger->info(sprintf('Changing tax rate isIncludedInPrice from « %s » to « %s »',
                        var_export($match->isIncludedInPrice(), true),
                        var_export($expectedTaxRate->isIncludedInPrice(), true)
                    ));
                    $match->setIncludedInPrice($expectedTaxRate->isIncludedInPrice());
                }
            } else {
                $logger->info(sprintf('Adding tax rate with code « %s »', $expectedTaxRate->getCode()));
                $actual->addRate($expectedTaxRate);
            }
        }

        return $migrations;
    }

    /**
     * @return TaxRate|null
     */
    private function lookupTaxRate(TaxCategory $category, TaxRate $rate)
    {
        $matches = [];

        foreach ($category->getRates() as $r) {
            if ($r instanceof TaxRate) {
                if ($r->getAmount() === $rate->getAmount() && $r->getCountry() === $rate->getCountry()) {
                    $matches[] = $r;
                }
            }
        }

        if (count($matches) === 0) {
            return null;
        }

        if (count($matches) === 1) {
            return current($matches);
        }

        // This happens when there is GST/PST with the same rate amount
        foreach ($matches as $r) {
            if ($rate->getCode() === $r->getCode()) {
                return $r;
            }
        }

        throw new \LogicException(sprintf('Could not lookup tax rate with amount %f for tax category « %s »',
            $rate->getAmount(),
            $category->getCode()));
    }
}
