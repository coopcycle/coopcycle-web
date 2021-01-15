<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Sylius\Taxation\TaxesHelper;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ObjectRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use League\Csv\Writer as CsvWriter;
use Sylius\Component\Order\Model\Adjustment;
use Symfony\Contracts\Translation\TranslatorInterface;

class RestaurantStats implements \IteratorAggregate, \Countable
{
    private $qb;
    private $result;
    private $orders;
    private $translator;
    private $withVendorName;
    private $withMessenger;

    private $columnTotals = [];
    private $taxTotals = [];
    private $taxColumns = [];

    private $numberFormatter;

    private $orderTotalResult;
    private $adjustmentTotalResult;

    public function __construct(
        string $locale,
        QueryBuilder $qb,
        ObjectRepository $taxRateRepository,
        TranslatorInterface $translator,
        bool $withVendorName = false,
        bool $withMessenger = false)
    {
        $this->qb = $qb;
        $this->result = $qb->getQuery()->getResult();

        $this->orders = new Paginator($qb->getQuery());
        $this->ordersIterator = $this->orders->getIterator();

        $this->translator = $translator;
        $this->withVendorName = $withVendorName;
        $this->withMessenger = $withMessenger;
        $this->taxesHelper = new TaxesHelper($taxRateRepository, $translator);

        $this->numberFormatter = \NumberFormatter::create($locale, \NumberFormatter::DECIMAL);
        $this->numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $this->numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        $this->ids = $this->loadIds();

        $this->addAdjustments();
        $this->computeTaxes();
        $this->computeColumnTotals();

        if ($withMessenger) {
            $this->loadMessengers();
        }
    }

    private function addAdjustments()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->qb->getEntityManager()
            ->getRepository(Adjustment::class)
            ->createQueryBuilder('a');

        $qb
            ->select('a.type')
            ->addSelect('a.amount')
            ->addSelect('a.neutral')
            ->addSelect('COALESCE(IDENTITY(a.order), IDENTITY(oi.order)) AS order_id')
            ->addSelect('IDENTITY(a.orderItem) AS order_item_id')
            ->addSelect('a.originCode AS origin_code')
            ->leftJoin(OrderItem::class, 'oi', Expr\Join::WITH, 'a.orderItem = oi.id')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in('oi.order', $this->ids),
                    $qb->expr()->in('a.order', $this->ids)
                )
            )
            ;

        $adjustments = $qb->getQuery()->getArrayResult();

        $adjustmentsByOrderId = array_reduce($adjustments, function ($accumulator, $adjustment) {

            if (!isset($accumulator[$adjustment['order_id']])) {
                $accumulator[$adjustment['order_id']] = [];
            }

            $accumulator[$adjustment['order_id']][] = $adjustment;

            return $accumulator;

        }, []);

        $this->result = array_map(function ($order) use ($adjustmentsByOrderId) {

            $order->adjustments = $adjustmentsByOrderId[$order->id];

            return $order;

        }, $this->result);
    }

    private function computeTaxes()
    {
        foreach ($this->result as $order) {

            $taxAdjustments = array_filter($order->adjustments, fn($adjustment) => $adjustment['type'] === 'tax');
            $taxRateCodes = array_map(fn($adjustment) => $adjustment['origin_code'], $taxAdjustments);

            $this->taxTotals[$order->getId()] = array_combine(
                $taxRateCodes,
                array_pad([], count($taxRateCodes), 0)
            );

            foreach ($taxAdjustments as $adjustment) {
                $this->taxTotals[$order->getId()][$adjustment['origin_code']] += $adjustment['amount'];
            }

            $this->taxColumns = array_merge($this->taxColumns, $taxRateCodes);
        }

        $this->taxColumns = array_unique($this->taxColumns);
    }

    private function computeColumnTotals()
    {
        foreach ($this->getColumns() as $column) {

            if (!$this->isNumericColumn($column)) {
                continue;
            }

            $this->columnTotals[$column] = 0;

            foreach ($this->result as $index => $order) {

                $rowValue = $this->getRowValue($column, $index, false);

                $this->columnTotals[$column] += $rowValue;
            }
        }
    }

    private function loadIds()
    {
        $qbForIds = clone $this->qb;
        $qbForIds->select('ov.id')->setFirstResult(null)->setMaxResults(null);

        return array_map(fn($row) => $row['id'], $qbForIds->getQuery()->getArrayResult());
    }

    private function loadMessengers()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->qb->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u');

        $qb
            ->select('IDENTITY(d.order) AS order_id')
            ->addSelect('t.id AS task_id')
            ->addSelect('u.username')
            ->innerJoin(Task::class,     't', Expr\Join::WITH, 't.assignedTo = u.id')
            ->innerJoin(Delivery::class, 'd', Expr\Join::WITH, 't.delivery = d.id')
            ->andWhere(
                $qb->expr()->in('d.order', $this->ids)
            )
            ->andWhere('t.type = :type')
            ->setParameter('type', Task::TYPE_DROPOFF)
            ;

        $result = $qb->getQuery()->getArrayResult();

        $this->messengers = array_reduce($result, function ($messengers, $value) {

            $messengers[$value['order_id']] = $value['username'];

            return $messengers;

        }, []);
    }

    private function isTaxColumn($column)
    {
        return in_array($column, $this->taxColumns);
    }

    private function formatNumber(int $amount, $bypass = false)
    {
        return $bypass ? $amount : $this->numberFormatter->format($amount / 100);
    }

    public function count()
    {
        return count($this->orders);
    }

    public function getIterator()
    {
        return $this->ordersIterator;
    }

    public function getColumns()
    {
        $headings = [];

        if ($this->withVendorName) {
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

            return $this->taxesHelper->translate($column);
        }

        return $this->translator->trans(sprintf('order.export.heading.%s', $column));
    }

    public function getRowValue($column, $index, $formatted = true)
    {
        $order = $this->ordersIterator->offsetGet($index);

        if ($this->isTaxColumn($column)) {

            return $formatted ? $this->formatNumber(
                $this->taxTotals[$order->getId()][$column] ?? 0
            ) : $this->taxTotals[$order->getId()][$column] ?? 0;
        }

        switch ($column) {
            case 'restaurant_name';
                return $order->hasVendor() ? $order->vendorName : '';
            case 'order_number';
                return $order->getNumber();
            case 'fulfillment_method';
                return $order->getFulfillmentMethod();
            case 'completed_by';
                return $order->getFulfillmentMethod() === 'delivery' ? ($this->messengers[$order->getId()] ?? '') : '';
            case 'completed_at';
                return $order->getShippedAt()->format('Y-m-d H:i');
            case 'total_products_excl_tax':
                return $this->formatNumber($order->getItemsTotal() - $order->getItemsTaxTotal(), !$formatted);
            case 'total_products_incl_tax':
                return $this->formatNumber($order->getItemsTotal(), !$formatted);
            case 'delivery_fee':
                return $this->formatNumber($order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_ADJUSTMENT), !$formatted);
            case 'packaging_fee':
                return $this->formatNumber($order->getAdjustmentsTotalRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT), !$formatted);
            case 'tip':
                return $this->formatNumber($order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT), !$formatted);
            case 'total_incl_tax':
                return $this->formatNumber($order->getTotal(), !$formatted);
            case 'stripe_fee':
                return $this->formatNumber($order->getStripeFeeTotal(), !$formatted);
            case 'platform_fee':
                return $this->formatNumber($order->getFeeTotal(), !$formatted);
            case 'net_revenue':
                return $this->formatNumber($order->getRevenue(), !$formatted);
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

        if (isset($this->columnTotals[$column])) {
            return $this->formatNumber($this->columnTotals[$column]);
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

        foreach ($this->result as $index => $order) {
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
