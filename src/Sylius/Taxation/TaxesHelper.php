<?php

namespace AppBundle\Sylius\Taxation;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaxesHelper
{
    private $translator;

    public function __construct(ObjectRepository $taxRateRepository, TranslatorInterface $translator)
    {
        $this->taxRateRepository = $taxRateRepository;
        $this->translator = $translator;
    }

    /**
     * @return array
     */
    public function getTaxTotals(OrderInterface $order, bool $itemsOnly = false): array
    {
        $taxRateCodes = [];
        foreach ($order->getItems() as $item) {
            foreach ($item->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $adj) {
                $taxRateCodes[] = $adj->getOriginCode();
            }
        }

        if (!$itemsOnly) {
            foreach ($order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $adj) {
                $taxRateCodes[] = $adj->getOriginCode();
            }
        }

        $taxRateCodes = array_unique($taxRateCodes);
        $taxRates = array_map(
            fn(string $code) => $this->taxRateRepository->findOneByCode($code),
            $taxRateCodes
        );

        $values = [];
        foreach ($taxRates as $taxRate) {

            $taxTotal = $order->getTaxTotalByRate($taxRate);

            if ($taxTotal > 0) {

                $key = sprintf('%s %d%%',
                    $this->translator->trans($taxRate->getName(), [], 'taxation'),
                    ($taxRate->getAmount() * 100)
                );

                if (isset($values[$key])) {
                    $values[$key] += $taxTotal;
                } else {
                    $values[$key] = $taxTotal;
                }
            }
        }

        return $values;
    }

    public function translate($code): string
    {
        $taxRate = $this->taxRateRepository->findOneByCode($code);

        return sprintf('%s %d%%',
            $this->translator->trans($taxRate->getName(), [], 'taxation'),
            ($taxRate->getAmount() * 100)
        );
    }
}
