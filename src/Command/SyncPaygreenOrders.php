<?php

namespace AppBundle\Command;

use AppBundle\Service\PaygreenManager;
use Paygreen\Sdk\Payment\V3\Client as PaygreenClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncPaygreenOrders extends Command
{
    /**
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    private $io;

    public function __construct(private PaygreenManager $paygreenManager, private PaygreenClient $paygreenClient)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:paygreen:reconciliate')
            ->setDescription('Reconciliates Paygreen orders')
            ->addOption(
                'start',
                null,
                InputOption::VALUE_REQUIRED,
                'Start date'
            )
            ->addOption(
                'end',
                null,
                InputOption::VALUE_REQUIRED,
                'End date'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = $input->getOption('start');
        $end = $input->getOption('end');

        $this->paygreenManager->authenticate();

        $paymentOrders = $this->loadPaymentOrders($start, $end);

        $table = new Table($output);
        $table->setHeaders(['Created at', 'Description', 'Status', 'Fees']);

        $feesTotal = 0;

        foreach ($paymentOrders as $paymentOrder) {
            $response = $this->paygreenClient->getPaymentOrder($paymentOrder['id']);
            $data = json_decode($response->getBody()->getContents(), true);

            $status = str_replace('payment_order.', '', $data['data']['status']);

            $table->addRow([
                $data['data']['created_at'],
                $data['data']['description'],
                $status,
                $data['data']['fees'],
            ]);

            if ('successed' === $status) {
                $feesTotal += $data['data']['fees'];
            }
        }

        $table->addRow([
            '',
            '',
            'Total fees',
            $feesTotal,
        ]);

        $table->render();

        return 0;
    }

    private function loadPaymentOrders($start, $end, int $page = 1, array $paymentOrders = [])
    {
        $this->io->text(sprintf('Loading page %d', $page));

        $response = $this->paygreenClient->listPaymentOrder(
            filters: ['start_at' => $start, 'end_at' => $end],
            pagination: ['max_per_page' => 50, 'page' => $page]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $paymentOrders = array_merge($paymentOrders, $data['data']);

        if ($data['pagination']['next']) {
            return $this->loadPaymentOrders($start, $end, $page + 1, $paymentOrders);
        }

        return $paymentOrders;
    }
}

