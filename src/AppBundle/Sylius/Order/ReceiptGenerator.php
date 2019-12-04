<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\Sylius\OrderReceipt;
use AppBundle\Entity\Sylius\OrderReceiptLineItem as LineItem;
use AppBundle\Entity\Sylius\OrderReceiptFooterItem as FooterItem;
use AppBundle\Sylius\Order\AdjustmentInterface;
use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class ReceiptGenerator
{
    public function __construct(
        TwigEnvironment $twig,
        Client $httpClient,
        Filesystem $filesystem,
        TranslatorInterface $translator,
        RepositoryInterface $taxRateRepository,
        string $locale)
    {
        $this->twig = $twig;
        $this->httpClient = $httpClient;
        $this->filesystem = $filesystem;
        $this->translator = $translator;
        $this->taxRateRepository = $taxRateRepository;
        $this->locale = $locale;
    }

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
                $receipt->addFooterItem(new FooterItem($taxRate->getName(), $taxTotal));
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

    public function generate(OrderReceipt $receipt, $filename): bool
    {
        $html = $this->twig->render('@App/order/receipt.pdf.twig', [
            'receipt'      => $receipt,
            'order_number' => $receipt->getOrder()->getNumber(),
            'payment'      => $receipt->getOrder()->getLastPayment(),
            'restaurant'   => $receipt->getOrder()->getRestaurant(),
            'locale'       => $this->locale,
        ]);

        $response = $this->httpClient->request('POST', '/pdf', [
            'json' => ['html' => $html]
        ]);

        if ($this->filesystem->has($filename)) {
            $this->filesystem->delete($filename);
        }

        return $this->filesystem->write($filename, (string) $response->getBody());
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
