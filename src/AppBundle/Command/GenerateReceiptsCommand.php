<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\ReceiptGenerator;
use Doctrine\Common\Persistence\ObjectManager;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateReceiptsCommand extends Command
{
    private $orderRepository;
    private $objectManager;
    private $receiptGenerator;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ObjectManager $objectManager,
        ReceiptGenerator $receiptGenerator)
    {
        $this->orderRepository = $orderRepository;
        $this->objectManager = $objectManager;
        $this->receiptGenerator = $receiptGenerator;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:generate-receipts')
            ->setDescription('Generate receipts for completed orders')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force generation'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->title('Generating receipts…');

        $orders = $this->orderRepository->findBy(['state' => 'fulfilled']);

        foreach ($orders as $order) {

            if ($order->hasReceipt() && !$input->getOption('force')) {
                $this->io->text(sprintf('Receipt for order #%d already exists', $order->getId()));
                continue;
            }

            if ($order->hasReceipt()) {
                $this->io->text(sprintf('Deleting previous receipt for order #%d', $order->getId()));
                $receipt = $order->removeReceipt();
                $this->objectManager->remove($receipt);
                $this->objectManager->flush();
            }

            $this->io->text(sprintf('Generating receipt for order #%d', $order->getId()));

            $receipt = $this->receiptGenerator->create($order);

            $order->setReceipt($receipt);

            $this->objectManager->flush();

            $filename = sprintf('%s.pdf', $order->getNumber());

            $this->receiptGenerator->generate($receipt, $filename);

            // $this->io->text(sprintf('Generated invoice with number %s', $invoice->getNumber()));
        }
    }
}
