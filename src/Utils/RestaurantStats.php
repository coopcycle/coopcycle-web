<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Taxation\TaxesHelper;
use AppBundle\Sylius\Order\AdjustmentInterface;
use League\Csv\Writer as CsvWriter;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RestaurantStats implements \IteratorAggregate, \Countable
{
    private $orders;
    private $translator;
    private $withRestaurantName;
    private $withMessenger;

    private $itemsTotal = 0;
    private $total = 0;
    private $itemsTaxTotal = 0;

    private $taxTotals = [];
    private $taxColumns = [];

    private $numberFormatter;

    public function __construct(
        string $locale,
        $orders,
        RepositoryInterface $taxRateRepository,
        TranslatorInterface $translator,
        bool $withRestaurantName = false,
        bool $withMessenger = false)
    {
        $this->orders = array_values($orders);
        $this->translator = $translator;
        $this->withRestaurantName = $withRestaurantName;
        $this->withMessenger = $withMessenger;

        $taxesHelper = new TaxesHelper($taxRateRepository, $translator);

        foreach ($orders as $index => $order) {
    		$this->itemsTotal += $order->getItemsTotal();
    		$this->total += $order->getTotal();
            $this->itemsTaxTotal += $order->getItemsTaxTotal();

            $taxTotals = $taxesHelper->getTaxTotals($order, $itemsOnly = true);

            $this->taxTotals[$index] = $taxTotals;
            $this->taxColumns = array_merge($this->taxColumns, array_keys($taxTotals));
        }

        $this->taxColumns = array_unique($this->taxColumns);

        $this->numberFormatter = \NumberFormatter::create($locale, \NumberFormatter::DECIMAL);
        $this->numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $this->numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
    }

    private function isTaxColumn($column)
    {
        return in_array($column, $this->taxColumns);
    }

    private function formatNumber(int $amount)
    {
        return $this->numberFormatter->format($amount / 100);
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

    public function getAdjustmentsTotalRecursively(?string $type = null): int
    {
        $total = 0;
        foreach ($this->orders as $order) {
            $total += $order->getAdjustmentsTotalRecursively($type);
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

    public function getColumns()
    {
        $headings = [];

        if ($this->withRestaurantName) {
            $headings[] = 'restaurant_name';
        }
        if ($this->withMessenger) {
            $headings[] = 'completed_by';
        }
        $headings[] = 'order_number';
        $headings[] = 'fulfillment_method';
        $headings[] = 'completed_at';
        $headings[] = 'total_products_excl_tax';
        foreach ($this->taxColumns as $taxLabel) {
            $headings[] = $taxLabel;
        }
        $headings[] = 'total_products_incl_tax';
        $headings[] = 'delivery_fee';
        $headings[] = 'packaging_fee';
        $headings[] = 'tip';
        $headings[] = 'total_incl_tax';
        $headings[] = 'stripe_fee';
        $headings[] = 'platform_fee';
        $headings[] = 'net_revenue';

        return $headings;
    }

    public function getColumnLabel($column)
    {
        if ($this->isTaxColumn($column)) {

            return $column;
        }

        return $this->translator->trans(sprintf('order.export.heading.%s', $column));
    }

    public function getRowValue($column, $index)
    {
        $order = $this->orders[$index];

        if ($this->isTaxColumn($column)) {

            return $this->formatNumber(
                $this->taxTotals[$index][$column] ?? 0
            );
        }

        switch ($column) {
            case 'restaurant_name';
                return null !== $order->getRestaurant() ? $order->getRestaurant()->getName() : '';
            case 'order_number';
                return $order->getNumber();
            case 'fulfillment_method';
                return $order->getFulfillmentMethod();
            case 'completed_by';
                if ($order->getFulfillmentMethod() === 'delivery') {
                    $messenger = $order->getDelivery()->getDropoff()->getAssignedCourier();
                    return $messenger ? $messenger->getUsername() : '';
                }
                return '';
            case 'completed_at';
                return $order->getShippedAt()->format('Y-m-d H:i');
            case 'total_products_excl_tax':
                return $this->formatNumber($order->getItemsTotal() - $order->getItemsTaxTotal());
            case 'total_products_incl_tax':
                return $this->formatNumber($order->getItemsTotal());
            case 'delivery_fee':
                return $this->formatNumber($order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT));
            case 'packaging_fee':
                return $this->formatNumber($order->getAdjustmentsTotalRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT));
            case 'tip':
                return $this->formatNumber($order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT));
            case 'total_incl_tax':
                return $this->formatNumber($order->getTotal());
            case 'stripe_fee':
                return $this->formatNumber($order->getStripeFeeTotal());
            case 'platform_fee':
                return $this->formatNumber($order->getFeeTotal());
            case 'net_revenue':
                return $this->formatNumber($order->getRevenue());
        }

        return '';
    }

    public function getColumnTotal($column)
    {
        if ($this->isTaxColumn($column)) {

            $total = array_reduce(
                $this->taxTotals,
                fn($carry, $taxTotals): int => $carry + ($taxTotals[$column] ?? 0),
                0
            );

            return $this->formatNumber($total);
        }

        switch ($column) {
            case 'total_products_excl_tax':
                return $this->formatNumber($this->getItemsTotal() - $this->getItemsTaxTotal());
            case 'total_products_incl_tax':
                return $this->formatNumber($this->getItemsTotal());
            case 'delivery_fee':
                return $this->formatNumber($this->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT));
            case 'packaging_fee':
                return $this->formatNumber($this->getAdjustmentsTotalRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT));
            case 'tip':
                return $this->formatNumber($this->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT));
            case 'total_incl_tax':
                return $this->formatNumber($this->getTotal());
            case 'stripe_fee':
                return $this->formatNumber($this->getStripeFeeTotal());
            case 'platform_fee':
                return $this->formatNumber($this->getFeeTotal());
            case 'net_revenue':
                return $this->formatNumber($this->getRevenue());
        }

        return '';
    }

    public function isNumericColumn($column)
    {
        if ($this->isTaxColumn($column)) {

            return true;
        }

        return in_array($column, [
            'total_products_excl_tax',
            'total_products_incl_tax',
            'delivery_fee',
            'packaging_fee',
            'tip',
            'total_incl_tax',
            'stripe_fee',
            'platform_fee',
            'net_revenue',
        ]);
    }

    public function toCsv()
    {
        $csv = CsvWriter::createFromString('');

        $headings = [];
        foreach ($this->getColumns() as $column) {
            $headings[] = $this->getColumnLabel($column);
        }

        $csv->insertOne($headings);

        $records = [];
        foreach ($this->orders as $index => $order) {

            $record = [];
            foreach ($this->getColumns() as $column) {
                $record[] = $this->getRowValue($column, $index);
            }

            $records[] = $record;
        }
        $csv->insertAll($records);

        return $csv->getContent();
    }
}
