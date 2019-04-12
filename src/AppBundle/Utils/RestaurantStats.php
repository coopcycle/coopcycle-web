<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\AdjustmentInterface;
use League\Csv\Writer as CsvWriter;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class RestaurantStats implements \IteratorAggregate, \Countable
{
    private $restaurant;
    private $orders;

    private $itemsTotal = 0;
    private $total = 0;
    private $itemsTaxTotal = 0;

    private $taxRates = [];

    public function __construct(Restaurant $restaurant, $orders, RepositoryInterface $taxRateRepository)
    {
        $this->restaurant = $restaurant;
        $this->orders = $orders;

    	foreach ($orders as $order) {
    		$this->itemsTotal += $order->getItemsTotal();
    		$this->total += $order->getTotal();
            $this->itemsTaxTotal += $order->getItemsTaxTotal();
    	}

        $taxRateCodes = [];
        foreach ($orders as $order) {
            foreach ($order->getItems() as $orderItem) {
                foreach ($orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $adjustment) {
                    $taxRateCodes[] = $adjustment->getOriginCode();
                }
            }
        }

        $taxRateCodes = array_unique($taxRateCodes);
        sort($taxRateCodes);

        foreach ($taxRateCodes as $taxRateCode) {
            $this->taxRates[] = $taxRateRepository->findOneByCode($taxRateCode);
        }
    }

    public function getItemsTotal(): int
    {
    	return $this->itemsTotal;
    }

    public function getTotal(): int
    {
    	return $this->total;
    }

    public function getItemsTaxTotal(): int
    {
        return $this->itemsTaxTotal;
    }

    public function getTaxTotalByRate($taxRate): int
    {
        $total = 0;
        foreach ($this->orders as $order) {
            $total += $order->getTaxTotalByRate($taxRate);
        }

        return $total;
    }

    public function getItemsTaxTotalByRate($taxRate): int
    {
        $total = 0;
        foreach ($this->orders as $order) {
            $total += $order->getItemsTaxTotalByRate($taxRate);
        }

        return $total;
    }

    public function getAdjustmentsTotal(?string $type = null): int
    {
        $total = 0;
        foreach ($this->orders as $order) {
            $total += $order->getAdjustmentsTotal($type);
        }

        return $total;
    }

    public function getFeeTotal(): int
    {
        $total = 0;
        foreach ($this->orders as $order) {
            $total += $order->getFeeTotal();
        }

        return $total;
    }

    public function getStripeFeeTotal(): int
    {
        $total = 0;
        foreach ($this->orders as $order) {
            $total += $order->getStripeFeeTotal();
        }

        return $total;
    }

    public function getRevenue(): int
    {
        $total = 0;
        foreach ($this->orders as $order) {
            $total += $order->getRevenue();
        }

        return $total;
    }

    public function count()
    {
        return count($this->orders);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->orders);
    }

    public function getTaxRates()
    {
        return $this->taxRates;
    }

    public function toCsv()
    {
        $csv = CsvWriter::createFromString('');

        $headings = [];
        $headings[] = 'Numéro de commande';
        $headings[] = 'Livrée le';
        $headings[] = 'Total produits HT';
        foreach ($this->getTaxRates() as $taxRate) {
            $headings[] = $taxRate->getName();
        }
        $headings[] = 'Total produits TTC';
        $headings[] = 'Livraison';
        $headings[] = 'Total TTC';
        $headings[] = 'Frais Stripe';
        $headings[] = 'Frais Plateforme';
        $headings[] = 'Revenu net';

        $csv->insertOne($headings);

        $records = [];
        foreach ($this->orders as $order) {

            $record = [];
            $record[] = $order->getNumber();
            $record[] = $order->getShippedAt()->format('Y-m-d H:i');
            $record[] = number_format(($order->getItemsTotal() - $order->getItemsTaxTotal()) / 100, 2);
            foreach ($this->getTaxRates() as $taxRate) {
                $record[] = number_format($order->getItemsTaxTotalByRate($taxRate) / 100, 2);
            }
            $record[] = number_format($order->getItemsTotal() / 100, 2);
            $record[] = number_format($order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT) / 100, 2);
            $record[] = number_format($order->getTotal() / 100, 2);
            $record[] = number_format($order->getStripeFeeTotal() / 100, 2);
            $record[] = number_format($order->getFeeTotal() / 100, 2);
            $record[] = number_format($order->getRevenue() / 100, 2);

            $records[] = $record;
        }
        $csv->insertAll($records);

        return $csv->getContent();
    }
}
