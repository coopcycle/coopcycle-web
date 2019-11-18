<?php

namespace AppBundle\Command;

use AppBundle\Entity\Invoice;
use AppBundle\Entity\Invoice\FooterItem;
use AppBundle\Entity\Invoice\LineItem;
use AppBundle\Entity\Invoice\Stakeholder;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Invoice\NumberGenerator;
use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment as TwigEnvironment;

class GenerateInvoicesCommand extends Command
{
    private $orderRepository;
    private $objectManager;
    private $twig;
    private $httpClient;
    private $invoicesFilesystem;
    private $numberGenerator;
    private $taxRateRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ObjectManager $objectManager,
        TwigEnvironment $twig,
        Client $httpClient,
        Filesystem $invoicesFilesystem,
        NumberGenerator $numberGenerator,
        RepositoryInterface $taxRateRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->objectManager = $objectManager;
        $this->twig = $twig;
        $this->httpClient = $httpClient;
        $this->invoicesFilesystem = $invoicesFilesystem;
        $this->numberGenerator = $numberGenerator;
        $this->taxRateRepository = $taxRateRepository;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:generate-invoices')
            ->setDescription('Generate invoices for completed orders');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->title('Generating invoices');

        $taxRates = $this->taxRateRepository->findAll();

        $orders = $this->orderRepository->findBy(['state' => 'fulfilled']);

        foreach ($orders as $order) {

            $invoice = new Invoice();
            $invoice->setNumber($this->numberGenerator->generate());
            $invoice->setOrderNumber($order->getNumber());
            $invoice->setTotal($order->getTotal());

            $emitter = new Stakeholder();
            $emitter->setName($order->getRestaurant()->getName());
            $emitter->setStreetAddress($order->getRestaurant()->getAddress()->getStreetAddress());

            $receiver = new Stakeholder();
            $receiver->setName($this->getReceiverName($order->getCustomer()));
            $receiver->setStreetAddress($order->getShippingAddress()->getStreetAddress());

            $invoice->setEmitter($emitter);
            $invoice->setReceiver($receiver);

            foreach ($order->getItems() as $orderItem) {
                $lineItem = new LineItem();
                $lineItem->setName($orderItem->getVariant()->getName());
                $lineItem->setDescription($this->getOrderItemDescription($orderItem));
                $lineItem->setQuantity($orderItem->getQuantity());
                $lineItem->setUnitPrice($orderItem->getUnitPrice());
                $lineItem->setSubtotal($orderItem->getTotal() - $orderItem->getTaxTotal());
                $lineItem->setTaxTotal($orderItem->getTaxTotal());
                $lineItem->setTotal($orderItem->getTotal());

                $invoice->addLineItem($lineItem);
            }

            $invoice->addFooterItem(new FooterItem('Total products', $order->getItemsTotal()));

            $deliveryAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);
            if (count($deliveryAdjustments) > 0) {
                $this->addAdjustmentFooterItem($invoice, $deliveryAdjustments->first());
            }

            foreach ($taxRates as $taxRate) {
                $taxTotal = $order->getTaxTotalByRate($taxRate);
                if ($taxTotal > 0) {
                    $invoice->addFooterItem(new FooterItem($taxRate->getName(), $taxTotal));
                }
            }
            $invoice->addFooterItem(new FooterItem('Total excl. tax', ($order->getTotal() - $order->getTaxTotal())));
            $invoice->addFooterItem(new FooterItem('Total incl. tax', $order->getTotal()));

            $order->addInvoice($invoice);

            $this->objectManager->flush();

            $html = $this->twig->render('@App/invoice/index.html.twig', [
                'invoice' => $invoice
            ]);

            $response = $this->httpClient->request('POST', '/pdf', ['json' => ['html' => $html]]);

            $filename = sprintf('%s.pdf', $invoice->getNumber());

            // if ($this->invoicesFilesystem->has($filename)) {
            //     $this->invoicesFilesystem->delete($filename);
            // }

            $this->invoicesFilesystem->write($filename, (string) $response->getBody());

            $this->io->text(sprintf('Generated invoice with number %s', $invoice->getNumber()));
        }
    }

    private function addAdjustmentFooterItem(Invoice $invoice, Adjustment $adjustment)
    {
        $invoice->addFooterItem(new FooterItem($adjustment->getLabel(), $adjustment->getAmount()));
    }

    private function getReceiverName($customer)
    {
        $fullName = trim(sprintf('%s %s', $customer->getGivenName(), $customer->getFamilyName()));

        if (empty($fullName)) {
            return $customer->getUsername();
        }

        return $fullName;
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
