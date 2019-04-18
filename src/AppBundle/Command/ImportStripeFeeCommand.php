<?php

namespace AppBundle\Command;

use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Stripe;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportStripeFeeCommand extends Command
{
    private $orderRepository;
    private $orderManager;

    public function __construct(
        RepositoryInterface $orderRepository,
        ObjectManager $orderManager,
        FactoryInterface $adjustmentFactory,
        SettingsManager $settingsManager)
    {
        $this->orderRepository = $orderRepository;
        $this->orderManager = $orderManager;
        $this->adjustmentFactory = $adjustmentFactory;

        $this->stripeLiveMode = $settingsManager->isStripeLivemode();

        Stripe\Stripe::setApiKey($settingsManager->get('stripe_secret_key'));

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:import-stripe-fee')
            ->setDescription('Import Stripe fee')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Dry run'
            )
            ->addOption(
                'date',
                'd',
                InputOption::VALUE_REQUIRED,
                'Date'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->title('Importing Stripe fees');

        $dryRun = $input->getOption('dry-run');

        $date = $this->getDate($input);

        if (is_array($date)) {
            [ $start, $end ] = $date;
            $this->io->text(sprintf('Retrieving orders fulfilled between %s and %s',
                $start->format('Y-m-d'), $end->format('Y-m-d')));
            $orders = $this->orderRepository->findFulfilledOrdersByDateRange($start, $end);
        } else {
            $this->io->text(sprintf('Retrieving orders fulfilled on %s',
                $date->format('Y-m-d')));
            $orders = $this->orderRepository->findFulfilledOrdersByDate($date);
        }

        $this->io->text(sprintf('Found %d orders to process', count($orders)));

        foreach ($orders as $order) {

            $this->io->section(sprintf('Importing Stripe fees for order #%d', $order->getId()));

            $lastPayment = $order->getLastPayment('completed');
            $stripeUserId = $lastPayment->getStripeUserId();

            $stripeOptions = [];
            if ($stripeUserId) {
                $stripeOptions['stripe_account'] = $stripeUserId;
            }

            try {

                $charge = Stripe\Charge::retrieve($lastPayment->getCharge(), $stripeOptions);
                $balanceTransaction = Stripe\BalanceTransaction::retrieve($charge->balance_transaction, $stripeOptions);

                $stripeFee = 0;
                foreach ($balanceTransaction->fee_details as $feeDetail) {
                    if ('stripe_fee' === $feeDetail->type) {
                        $stripeFee = $feeDetail->amount;
                        break;
                    }
                }

                if ($stripeFee > 0) {
                    $order->removeAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT);

                    $this->io->text(sprintf('Stripe fee = %d', $stripeFee));

                    $stripeFeeAdjustment = $this->adjustmentFactory->createWithData(
                        AdjustmentInterface::STRIPE_FEE_ADJUSTMENT,
                        'Stripe fee',
                        $stripeFee,
                        $neutral = true
                    );
                    $order->addAdjustment($stripeFeeAdjustment);

                    if (!$dryRun) {
                        $this->orderManager->flush();
                    }
                }

            } catch (\Exception $e) {
                $this->io->caution($e->getMessage());
            }
        }
    }

    private function getDate($input)
    {
        $dateOption = $input->getOption('date');

        if ($dateOption) {

            if (1 === preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $dateOption, $matches)) {
                return new \DateTime($dateOption);
            }

            if (1 === preg_match('/^([0-9]{4})-([0-9]{2})$/', $dateOption, $matches)) {
                $year = $matches[1];
                $month = $matches[2];

                $start = new \DateTime();
                $start->setDate($year, $month, 1);
                $start->setTime(0, 0, 0);

                $end = clone $start;
                $end->setDate($start->format('Y'), $start->format('m'), $start->format('t'));
                $end->setTime(23, 59, 59);

                return [ $start, $end ];
            }


            return false;
        }

        return new \DateTime('yesterday');
    }
}
