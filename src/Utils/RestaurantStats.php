<?php

namespace AppBundle\Utils;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Nonprofit;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Entity\Sylius\OrderView;
use AppBundle\Entity\Sylius\TaxRate;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use League\Csv\Writer as CsvWriter;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RestaurantStats implements \Countable
{
    private $result;
    private $translator;
    private $withVendorName;
    private $withMessenger;

    private $columnTotals = [];
    private $taxTotals = [];
    private $itemsTotalExclTaxTotals = [];
    private $taxColumns = [];
    private $productTaxColumns;

    private $numberFormatter;

    private bool $nonProfitsEnabled;
    private bool $withBillingMethod;

    const MAX_RESULTS = 50;

    public function __construct(
        EntityManagerInterface $entityManager,
        \DateTime $start,
        \DateTime $end,
        ?LocalBusiness $restaurant,
        PaginatorInterface $paginator,
        string $locale,
        TranslatorInterface $translator,
        TaxesHelper $taxesHelper,
        bool $withVendorName = false,
        bool $withMessenger = false,
        bool $nonProfitsEnabled = false,
        bool $withBillingMethod = false)
    {
        $this->entityManager = $entityManager;

        $this->paginator = $paginator;
        $this->translator = $translator;
        $this->taxesHelper = $taxesHelper;
        $this->withVendorName = $withVendorName;
        $this->withMessenger = $withMessenger;
        $this->nonProfitsEnabled = $nonProfitsEnabled;
        $this->withBillingMethod = $withBillingMethod;

        $this->numberFormatter = \NumberFormatter::create($locale, \NumberFormatter::DECIMAL);
        $this->numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $this->numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        $this->result = $this->getArrayResult($start, $end, $restaurant);
        $this->ids = array_map(fn ($o) => $o->id, $this->result);

        $this->addAdjustments();
        $this->addVendors();
        $this->addRefunds();
        $this->addStores();
        $this->addPaymentMethods();

        $this->computeTaxes();
        $this->computeColumnTotals();

        if ($withMessenger) {
            $this->loadMessengers();
        }

        if ($nonProfitsEnabled) {
            $this->addNonprofits();
        }
    }

    public function getPagination(int $page = 1): PaginationInterface
    {
        return $this->paginator->paginate($this->result, $page, self::MAX_RESULTS);
    }

    public function getPages(): int
    {
        $pagination = $this->getPagination();

        return intval(ceil($pagination->getTotalItemCount() / self::MAX_RESULTS));
    }

    private function addAdjustments()
    {
        if (count($this->ids) === 0) {
            return;
        }

        //
        // Add "regular" adjustments
        //

        $qb = $this->entityManager
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

        //
        // Add "virtual" adjustments with items total excl. tax
        //

        $qb = $this->entityManager
            ->getRepository(OrderItem::class)
            ->createQueryBuilder('oi');

        $qb
            ->select('IDENTITY(oi.order) AS order_id')
            ->addSelect('a.originCode AS tax_rate_code')
            ->addSelect('(SUM(oi.total) - SUM(a.amount)) AS items_total_excl_tax')
            ->leftJoin(Adjustment::class, 'a', Expr\Join::WITH, 'a.orderItem = oi.id')
            ->andWhere(
                $qb->expr()->in('oi.order', ':ids')
            )
            ->andWhere('a.type = :tax')
            ->groupBy('order_id', 'tax_rate_code')
            ->setParameter('ids', $this->ids)
            ->setParameter('tax', AdjustmentInterface::TAX_ADJUSTMENT);

        $totalExclTaxByRate = $qb->getQuery()->getArrayResult();

        $byOrderId = [];
        foreach ($totalExclTaxByRate as $entry) {
            $byOrderId[$entry['order_id']][] = $entry;
        }

        $this->result = array_map(function ($order) use ($byOrderId) {

            if (array_key_exists($order->id, $byOrderId)) {
                foreach ($byOrderId[$order->id] as $entry) {

                    $order->adjustments[] = [
                        'type'          => 'items_total_excl_tax',
                        'amount'        => $entry['items_total_excl_tax'],
                        'neutral'       => true,
                        'order_id'      => $order->id,
                        'order_item_id' => null,
                        'origin_code'   => $entry['tax_rate_code'],
                    ];
                }
            }

            return $order;

        }, $this->result);
    }

    private function addVendors()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->entityManager
            ->getRepository(OrderVendor::class)
            ->createQueryBuilder('v');

        $qb
            ->select('IDENTITY(v.order) AS order_id')
            ->addSelect('IDENTITY(v.restaurant) AS restaurant_id')
            ->addSelect('r.name AS restaurant_name')
            ->addSelect('r.billingMethod AS restaurant_billing_method')
            ->addSelect('IDENTITY(r.hub) AS hub_id')
            ->addSelect('h.name AS hub_name')
            ->addSelect('v.itemsTotal')
            ->addSelect('v.transferAmount')
            ->leftJoin(LocalBusiness::class, 'r', Expr\Join::WITH, 'v.restaurant = r.id')
            ->leftJoin(Hub::class, 'h', Expr\Join::WITH, 'r.hub = h.id')
            ->andWhere(
                $qb->expr()->in('v.order', $this->ids)
            );

        $vendors = $qb->getQuery()->getArrayResult();

        $vendorsByOrderId = array_reduce($vendors, function ($accumulator, $vendor) {

            if (!isset($accumulator[$vendor['order_id']])) {
                $accumulator[$vendor['order_id']] = [];
            }

            $accumulator[$vendor['order_id']][] = $vendor;

            return $accumulator;

        }, []);

        $this->result = array_map(function ($order) use ($vendorsByOrderId) {

            if (isset($vendorsByOrderId[$order->id])) {
                $order->vendors = $vendorsByOrderId[$order->id];
                $order->billingMethod = count($vendorsByOrderId[$order->id]) === 1 ? $vendorsByOrderId[$order->id][0]['restaurant_billing_method'] : 'unit';
            }

            return $order;

        }, $this->result);
    }

    private function addRefunds()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o');
        $qb
            ->select([
                'o.id AS order_id',
                'r.liableParty',
                'r.amount',
            ])
            ->join(PaymentInterface::class, 'p', Expr\Join::WITH, 'p.order = o.id')
            ->join(Refund::class,           'r', Expr\Join::WITH, 'r.payment = p.id')
            ->andWhere(
                $qb->expr()->in('o.id', ':ids')
            )
            ->setParameter('ids', $this->ids)
            ;

        $refunds = $qb->getQuery()->getArrayResult();

        $this->result = array_map(function ($order) use ($refunds) {

            foreach ($refunds as $refund) {
                if ($refund['order_id'] === $order->id) {
                    $order->refunds[] = $refund;
                }
            }

            return $order;

        }, $this->result);
    }

    private function addPaymentMethods()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o');
        $qb
            ->select([
                'o.id AS order_id',
                'pm.code',
            ])
            ->join(PaymentInterface::class, 'p', Expr\Join::WITH, 'p.order = o.id')
            ->join(PaymentMethodInterface::class, 'pm', Expr\Join::WITH, 'p.method = pm.id')
            ->andWhere(
                $qb->expr()->in('o.id', ':ids')
            )
            ->setParameter('ids', $this->ids)
            ;

        $paymentMethods = $qb->getQuery()->getArrayResult();
        $paymentMethods = array_column($paymentMethods, 'code', 'order_id');

        $this->result = array_map(function ($order) use ($paymentMethods) {

            $order->paymentMethod = $paymentMethods[$order->id] ?? null;

            return $order;

        }, $this->result);
    }

    private function addNonprofits()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->entityManager
            ->getRepository(Nonprofit::class)
            ->createQueryBuilder('np');

        $qb
            ->select('np.id')
            ->addSelect('np.name')
            ;

        $nonProfits = $qb->getQuery()->getArrayResult();

        $nonProfitsById = array_reduce($nonProfits, function ($accumulator, $nonProfit) {

            $accumulator[$nonProfit['id']] = $nonProfit['name'];

            return $accumulator;

        }, []);

        $this->result = array_map(function ($order) use ($nonProfitsById) {

            if (!empty($order->nonprofitId)) {
                $order->nonprofitName = $nonProfitsById[$order->nonprofitId];
            }

            return $order;

        }, $this->result);
    }

    private function addStores()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->entityManager
            ->getRepository(Delivery::class)
            ->createQueryBuilder('d');

        $qb
            ->select('IDENTITY(d.order) AS order_id')
            ->addSelect('s.name AS store_name')
            ->addSelect('s.billingMethod AS store_billing_method')
            ->join(Store::class, 's', Expr\Join::WITH, 'd.store = s.id')
            ->andWhere(
                $qb->expr()->in('d.order', $this->ids)
            );

        $stores = $qb->getQuery()->getArrayResult();

        $storesByOrderId = array_reduce($stores, function ($accumulator, $store) {

            if (!isset($accumulator[$store['order_id']])) {
                $accumulator[$store['order_id']] = [];
            }

            $accumulator[$store['order_id']] = [
                'name' => $store['store_name'],
                'billing_method' => $store['store_billing_method']
            ];

            return $accumulator;

        }, []);

        $this->result = array_map(function ($order) use ($storesByOrderId) {

            if (isset($storesByOrderId[$order->id])) {
                $order->storeName = $storesByOrderId[$order->id]['name'];
                $order->billingMethod = $storesByOrderId[$order->id]['billing_method'];
            }

            return $order;

        }, $this->result);
    }

    private function computeTaxes()
    {
        $this->serviceTaxRateCode = $this->taxesHelper->getServiceTaxRateCode();

        $productTaxColumns =
            array_map(fn (TaxRate $rate) => $rate->getCode(), $this->taxesHelper->getBaseRates());

        $this->taxColumns = array_merge(
            $productTaxColumns,
            [ $this->serviceTaxRateCode ]
        );

        $this->productTaxColumns = array_map(fn ($code) => sprintf('product.%s', $code), $productTaxColumns);

        foreach ($this->result as $order) {

            $taxAdjustments =
                array_filter($order->adjustments, fn($adjustment) => $adjustment['type'] === 'tax');

            $this->taxTotals[$order->getId()] = array_combine(
                $this->taxColumns,
                array_pad([], count($this->taxColumns), 0)
            );

            foreach ($taxAdjustments as $adjustment) {

                $taxRateCode = $adjustment['origin_code'];

                // This allows showing fewer columns
                if (!in_array($taxRateCode, $this->taxColumns)) {
                    $matchingBaseRateCode = $this->taxesHelper->getMatchingBaseRateCode($taxRateCode);
                    if (!empty($matchingBaseRateCode)) {
                        $taxRateCode = $matchingBaseRateCode;
                    }
                }

                $this->taxTotals[$order->getId()][$taxRateCode] += $adjustment['amount'];
            }

            $itemsTotalExclTaxAdjustments =
                array_filter($order->adjustments, fn($adjustment) => $adjustment['type'] === 'items_total_excl_tax');

            $this->itemsTotalExclTaxTotals[$order->getId()] = array_combine(
                $this->productTaxColumns,
                array_pad([], count($this->productTaxColumns), 0)
            );

            foreach ($itemsTotalExclTaxAdjustments as $adjustment) {

                $taxRateCode = $adjustment['origin_code'];

                // This allows showing fewer columns
                if (!in_array($taxRateCode, $this->taxColumns)) {
                    $taxRateCode = $this->taxesHelper->getMatchingBaseRateCode($taxRateCode);
                }

                $this->itemsTotalExclTaxTotals[$order->getId()][$taxRateCode] = $adjustment['amount'];
            }
        }
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

    private function loadMessengers()
    {
        if (count($this->ids) === 0) {
            return;
        }

        $qb = $this->entityManager
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

    public function isTaxColumn($column)
    {
        return in_array($column, $this->taxColumns);
    }

    public function isProductTaxColumn($column)
    {
        return in_array($column, $this->productTaxColumns);
    }

    private function formatNumber(int $amount, $bypass = false)
    {
        return $bypass ? $amount : $this->numberFormatter->format($amount / 100);
    }

    public function count(): int
    {
        return count($this->result);
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
        foreach ($this->productTaxColumns as $code) {
            $headings[] = $code;
        }
        $headings[] = 'total_products_excl_tax';
        foreach ($this->taxColumns as $code) {
            if ($code === $this->serviceTaxRateCode) {
                continue;
            }
            $headings[] = $code;
        }
        $headings[] = 'total_products_incl_tax';
        $headings[] = 'delivery_fee';
        $headings[] = $this->serviceTaxRateCode;
        $headings[] = 'packaging_fee';
        $headings[] = 'tip';
        $headings[] = 'promotions';
        $headings[] = 'total_incl_tax';
        $headings[] = 'payment_method';
        $headings[] = 'stripe_fee';
        $headings[] = 'platform_fee';
        $headings[] = 'refund_total';
        $headings[] = 'net_revenue';
        if ($this->nonProfitsEnabled) {
            $headings[] = 'nonprofit';
        }
        if ($this->withBillingMethod) {
            $headings[] = 'billing_method';
            $headings[] = 'applied_billing';
        }

        return $headings;
    }

    public function getColumnLabel($column)
    {
        if ($this->isTaxColumn($column)) {

            return $this->taxesHelper->translate($column);
        }

        if ($this->isProductTaxColumn($column)) {
            $code = str_replace('product.', '', $column);

            return $this->taxesHelper->translate($code);
        }

        return $this->translator->trans(sprintf('order.export.heading.%s', $column));
    }

    public function getRowValue($column, $index, $formatted = true)
    {
        $order = $this->result[$index];

        if ($this->isTaxColumn($column)) {

            return $formatted ? $this->formatNumber(
                $this->taxTotals[$order->getId()][$column] ?? 0
            ) : $this->taxTotals[$order->getId()][$column] ?? 0;
        }

        if ($this->isProductTaxColumn($column)) {
            $code = str_replace('product.', '', $column);

            return $formatted ? $this->formatNumber(
                $this->itemsTotalExclTaxTotals[$order->getId()][$code] ?? 0
            ) : $this->itemsTotalExclTaxTotals[$order->getId()][$code] ?? 0;
        }

        switch ($column) {
            case 'restaurant_name';
                return $order->hasVendor() ? $order->getVendorName() : $order->storeName;
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
                return $this->formatNumber($order->getAdjustmentsTotal(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT), !$formatted);
            case 'tip':
                return $this->formatNumber($order->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT), !$formatted);
            case 'promotions':
                $promotionsTotal =
                $order->getAdjustmentsTotal(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT)
                    +
                    $order->getAdjustmentsTotal(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
                return $this->formatNumber($promotionsTotal, !$formatted);
            case 'total_incl_tax':
                return $this->formatNumber($order->getTotal(), !$formatted);
            case 'stripe_fee':
                return $this->formatNumber($order->getStripeFeeTotal(), !$formatted);
            case 'platform_fee':
                return $this->formatNumber($order->getFeeTotal(), !$formatted);
            case 'refund_total':
                return $this->formatNumber($order->getRefundTotal(), !$formatted);
            case 'net_revenue':
                return $this->formatNumber($order->getRevenue(), !$formatted);
            case 'nonprofit':
                return $order->getNonprofit();
            case 'payment_method':
                return $order->paymentMethod ? $this->translator->trans(sprintf('payment_method.%s', strtolower($order->paymentMethod))) : '';
            case 'billing_method':
                return $order->billingMethod ?? 'unit';
            case 'applied_billing':
                return $order->hasVendor() ? Order::STORE_TYPE_FOODTECH : Order::STORE_TYPE_LASTMILE;
        }

        return '';
    }

    public function getRowValueForPage($column, $index, $page = 1, $formatted = true)
    {
        $offset = ($page - 1) * self::MAX_RESULTS;

        return $this->getRowValue($column, ($offset + $index), $formatted);
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
        if ($this->isTaxColumn($column) || $this->isProductTaxColumn($column)) {

            return true;
        }

        return in_array($column, [
            'total_products_excl_tax',
            'total_products_incl_tax',
            'delivery_fee',
            'packaging_fee',
            'tip',
            'promotions',
            'total_incl_tax',
            'stripe_fee',
            'platform_fee',
            'refund_total',
            'net_revenue'
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

    /**
     * This will load the orders as an *ARRAY* (not as object).
     * We do this to avoid using too much memory (loading nested objects, etc...)
     */
    private function getArrayResult(\DateTime $start, \DateTime $end, ?LocalBusiness $restaurant = null): array
    {
        $rsm = new ResultSetMappingBuilder($this->entityManager, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);

        $rsm->addRootEntityFromClassMetadata(Order::class, 'o');
        $rsm->addJoinedEntityResult(OrderVendor::class, 'v', 'o', 'vendors');

        $sql = 'SELECT ' . $rsm->generateSelectClause() . ' '
            . 'FROM sylius_order o '
            . 'LEFT JOIN sylius_order_vendor v ON (o.id = v.order_id) '
            . 'LEFT JOIN sylius_order_event evt ON (o.id = evt.aggregate_id AND type = :event_type) '
            . 'WHERE '
            . 'evt.created_at BETWEEN :start AND :end '
            . 'AND o.state = :state'
            ;

        if (null !== $restaurant) {
            $sql .= ' AND v.restaurant_id = :restaurant';
        }

        $sql .= ' ORDER BY o.shipping_time_range DESC';

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('state', OrderInterface::STATE_FULFILLED);
        $query->setParameter('event_type', OrderFulfilled::messageName());
        $query->setParameter('start', $start);
        $query->setParameter('end', $end);
        if (null !== $restaurant) {
            $query->setParameter('restaurant', $restaurant);
        }

        $result = $query->getArrayResult();

        $orders = array_map(fn ($data) => OrderView::create($data, $restaurant), $result);

        return $orders;
    }
}
