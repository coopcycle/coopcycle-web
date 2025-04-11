<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Sylius\OrderReceipt;
use AppBundle\Entity\Sylius\OrderReceiptLineItem as LineItem;
use AppBundle\Entity\Sylius\OrderReceiptFooterItem as FooterItem;
use AppBundle\Sylius\Order\AdjustmentInterface;
use League\Flysystem\Filesystem;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class ReceiptGenerator
{
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
        $taxRates = $this->taxRateRepository->findAll();

        $receipt = new OrderReceipt();

        foreach ($order->getItems() as $orderItem) {
            $lineItem = new LineItem();
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
            new FooterItem($this->translator->trans('receipt.footer_item.total_products'), $order->getItemsTotal())
        );

        $deliveryAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        if (count($deliveryAdjustments) > 0) {
            $this->addAdjustmentFooterItem($receipt, $deliveryAdjustments->first());
        }

        foreach ($taxRates as $taxRate) {
            $taxTotal = $order->getTaxTotalByRate($taxRate);
            if ($taxTotal > 0) {
                $receipt->addFooterItem(
                    new FooterItem(
                        sprintf('%s - %s%%',
                            $this->translator->trans($taxRate->getName(), [], 'taxation'),
                            number_format((float) ($taxRate->getAmount() * 100), 2)
                        ),
                        $taxTotal
                    )
                );
            }
        }
        $receipt->addFooterItem(
            new FooterItem($this->translator->trans('receipt.footer_item.total_excl_tax'), ($order->getTotal() - $order->getTaxTotal()))
        );
        $receipt->addFooterItem(
            new FooterItem($this->translator->trans('receipt.footer_item.total_incl_tax'), $order->getTotal())
        );

        return $receipt;
    }

    public function generate(OrderInterface $order, $filename)
    {
        if ($this->filesystem->fileExists($filename)) {
            $this->filesystem->delete($filename);
        }

        $this->filesystem->write($filename, $this->render($order));
    }

    public function render(OrderInterface $order): string
    {
        if (!$order->hasReceipt()) {
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

    private function addAdjustmentFooterItem(OrderReceipt $receipt, Adjustment $adjustment)
    {
        $receipt->addFooterItem(new FooterItem($adjustment->getLabel(), $adjustment->getAmount()));
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
