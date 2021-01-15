<?php

namespace AppBundle\Twig;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Twig\Extension\RuntimeExtensionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaxRateRuntime implements RuntimeExtensionInterface
{
    private $taxesHelper;

    public function __construct(RepositoryInterface $taxRateRepository, TranslatorInterface $translator)
    {
        $this->taxesHelper = new TaxesHelper($taxRateRepository, $translator);
    }

    public function split($order)
    {
        $taxTotals = $this->taxesHelper->getTaxTotals($order, $itemsOnly = false);

        return array_map(fn($name, $amount) => [
            'name' => $name,
            'amount' => $amount,
        ], array_keys($taxTotals), $taxTotals);
    }

    public function name($code)
    {
        $taxRate = $this->taxRateRepository->findOneByCode($code);

        if ($taxRate) {
            return $taxRate->getName();
        }
    }
}
