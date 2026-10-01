<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Sylius\OrderReceipt;
use AppBundle\Entity\Sylius\OrderReceiptLineItem as LineItem;
use AppBundle\Entity\Sylius\OrderReceiptFooterItem as FooterItem;
use AppBundle\Sylius\Order\AdjustmentInterface;
use League\Flysystem\Filesystem;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class ReceiptGenerator
{
    /**
     * The adjustments billed to the customer on top of the products,
     * in the order they are listed on the receipt.
     */
    private const SERVICE_ADJUSTMENTS = [
        AdjustmentInterface::DELIVERY_ADJUSTMENT,
        AdjustmentInterface::TIP_ADJUSTMENT,
        AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
    ];

    public function __construct(
        private TwigEnvironment $twig,
        private HttpClientInterface $browserlessClient,
        private Filesystem $filesystem,
        private TranslatorInterface $translator,
        private RepositoryInterface $taxRateRepository,
        private string $locale)
    {}

    public function create(OrderInterface $order): OrderReceipt
    {
        $receipt = new OrderReceipt();

        $customer = $order->getCustomer();
        if (null !== $customer) {
            $fullName = trim($customer->getFullName());
            $receipt->setCustomerName('' !== $fullName ? $fullName : null);
            $receipt->setCustomerEmail($customer->getEmail());
            $receipt->setCustomerPhone($customer->getPhoneNumber());
        }

        foreach ($order->getItems() as $orderItem) {
            $lineItem = new LineItem();
            $lineItem->setType(LineItem::TYPE_PRODUCT);
            $lineItem->setName($orderItem->getVariant()->getName());
            $lineItem->setDescription($this->getOrderItemDescription($orderItem));
            $lineItem->setQuantity($orderItem->getQuantity());
            $lineItem->setUnitPrice($orderItem->getUnitPrice());
            $lineItem->setSubtotal($orderItem->getTotal() - $orderItem->getTaxTotal());
            $lineItem->setTaxTotal($orderItem->getTaxTotal());
            $lineItem->setTotal($orderItem->getTotal());

            $receipt->addLineItem($lineItem);
        }

        $receipt->addFooterItem(
            new FooterItem(
                $this->translator->trans('receipt.footer_item.total_products'),
                $order->getItemsTotal(),
                FooterItem::SECTION_PRODUCTS
            )
        );

        $servicesTotal = 0;
        $serviceTaxTotals = $this->getServiceTaxTotals($order);

        foreach (self::SERVICE_ADJUSTMENTS as $adjustmentType) {
            foreach ($order->getAdjustments($adjustmentType) as $adjustment) {
                if (0 === $adjustment->getAmount()) {
                    continue;
                }

                $taxTotal = $serviceTaxTotals[spl_object_id($adjustment)] ?? 0;

                $lineItem = new LineItem();
                $lineItem->setType(LineItem::TYPE_SERVICE);
                $lineItem->setName($adjustment->getLabel());
                $lineItem->setQuantity(1);
                $lineItem->setUnitPrice($adjustment->getAmount());
                $lineItem->setSubtotal($adjustment->getAmount() - $taxTotal);
                $lineItem->setTaxTotal($taxTotal);
                $lineItem->setTotal($adjustment->getAmount());

                $receipt->addLineItem($lineItem);

                $servicesTotal += $adjustment->getAmount();
            }
        }

        if ($servicesTotal > 0) {
            $receipt->addFooterItem(
                new FooterItem(
                    $this->translator->trans('receipt.footer_item.total_services'),
                    $servicesTotal,
                    FooterItem::SECTION_SERVICES
                )
            );
        }

        $receipt->addFooterItem(
            new FooterItem(
                $this->translator->trans('receipt.footer_item.total_excl_tax'),
                ($order->getTotal() - $order->getTaxTotal()),
                FooterItem::SECTION_TOTALS
            )
        );

        foreach ($this->getTaxTotalsByRateName($order) as $name => $taxTotal) {
            $receipt->addFooterItem(
                new FooterItem($name, $taxTotal, FooterItem::SECTION_TOTALS)
            );
        }

        $receipt->addFooterItem(
            new FooterItem(
                $this->translator->trans('receipt.footer_item.total_incl_tax'),
                $order->getTotal(),
                FooterItem::SECTION_TOTALS
            )
        );

        return $receipt;
    }

    /**
     * Several tax rates may share the same name (for instance one "Taux
     * intermédiaire" per tax category), but the receipt shows one line per
     * name. Returns the totals keyed by name, sorted by increasing rate.
     *
     * @return array<string, int>
     */
    private function getTaxTotalsByRateName(OrderInterface $order): array
    {
        $rows = [];

        foreach ($this->taxRateRepository->findAll() as $taxRate) {
            /** @var TaxRateInterface $taxRate */
            $taxTotal = $order->getTaxTotalByRate($taxRate);
            if ($taxTotal <= 0) {
                continue;
            }

            $name = $this->translator->trans($taxRate->getName(), [], 'taxation');

            if (!isset($rows[$name])) {
                $rows[$name] = ['amount' => $taxRate->getAmount(), 'total' => 0];
            }

            $rows[$name]['total'] += $taxTotal;
        }

        uasort($rows, fn($a, $b) => $a['amount'] <=> $b['amount']);

        return array_map(fn($row) => $row['total'], $rows);
    }

    /**
     * OrderTaxesProcessor adds one order level tax adjustment per taxable
     * service adjustment, in the very same order, so we pair them up to know
     * how much tax each service line carries.
     *
     * @return array<int, int> tax total, keyed by service adjustment object id
     */
    private function getServiceTaxTotals(OrderInterface $order): array
    {
        $taxableAdjustments = array_merge(
            $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT)->getValues(),
            $order->getAdjustments(AdjustmentInterface::INCIDENT_ADJUSTMENT)->getValues()
        );

        $taxAdjustments = $order->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->getValues();

        $taxTotals = [];
        foreach ($taxableAdjustments as $index => $adjustment) {
            if (isset($taxAdjustments[$index])) {
                $taxTotals[spl_object_id($adjustment)] = $taxAdjustments[$index]->getAmount();
            }
        }

        return $taxTotals;
    }

    public function generate(OrderInterface $order, $filename)
    {
        if ($this->filesystem->fileExists($filename)) {
            $this->filesystem->delete($filename);
        }

        $this->filesystem->write($filename, $this->render($order));
    }

    public function render(OrderInterface $order, bool $create = false): string
    {
        if (!$order->hasReceipt() || $create) {
            $order->setReceipt(
                $this->create($order)
            );
        }

        $html = $this->twig->render('order/receipt.pdf.twig', [
            'receipt'      => $order->getReceipt(),
            'order_number' => $order->getNumber(),
            'payments'     => $order->getPayments(),
            'restaurant'   => $order->getRestaurant(),
            'locale'       => $this->locale,
        ]);

        $response = $this->browserlessClient->request('POST', '/pdf', [
            'json' => ['html' => $html]
        ]);

        return $response->getContent();
    }

    private function getOrderItemDescription(AdjustableInterface $adjustable)
    {
        $options = $adjustable->getAdjustments('menu_item_modifier');

        $lines = [];
        foreach ($options as $option) {
            $lines[] = $option->getLabel();
        }

        return implode("\n", $lines);
    }
}
