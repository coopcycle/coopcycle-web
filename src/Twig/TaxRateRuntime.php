<?php

namespace AppBundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class TaxRateRuntime implements RuntimeExtensionInterface
{
    private $taxRateRepository;

    public function __construct(RepositoryInterface $taxRateRepository)
    {
        $this->taxRateRepository = $taxRateRepository;
    }

    public function split($order)
    {
        $taxRates = $this->taxRateRepository->findAll();

        $values = [];
        foreach ($taxRates as $taxRate) {
            $taxTotal = $order->getTaxTotalByRate($taxRate);
            if ($taxTotal > 0) {
                $values[] = [
                    'name' => $taxRate->getName(),
                    'amount' => $taxTotal,
                ];
            }
        }

        return $values;
    }

    public function splitItems($order)
    {
        $taxRates = $this->taxRateRepository->findAll();

        $values = [];
        foreach ($taxRates as $taxRate) {
            $taxTotal = $order->getItemsTaxTotalByRate($taxRate);
            if ($taxTotal > 0) {
                $values[] = [
                    'name' => $taxRate->getName(),
                    'amount' => $taxTotal,
                ];
            }
        }

        return $values;
    }

    public function name($code)
    {
        $taxRate = $this->taxRateRepository->findOneByCode($code);

        if ($taxRate) {
            return $taxRate->getName();
        }
    }
}
