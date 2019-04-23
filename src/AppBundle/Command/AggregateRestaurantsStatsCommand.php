<?php

namespace AppBundle\Command;

use Doctrine\Common\Persistence\ObjectManager;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AggregateRestaurantsStatsCommand extends Command
{
    private $orderRepository;
    private $orderManager;

    public function __construct(
        RepositoryInterface $orderRepository,
        ObjectManager $orderManager)
    {
        $this->orderRepository = $orderRepository;
        $this->orderManager = $orderManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:restaurants:aggregate-stats')
            ->setDescription('Aggregate restaurants stats')
            ->addOption(
                'month',
                'm',
                InputOption::VALUE_REQUIRED,
                'Month'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->title('Aggregating restaurants stats');

        $range = $this->getDateRange($input);

        if (false === $range) {
            $this->io->error('Option must be in YYYY-MM format');

            return 1;
        }

        [ $start, $end ] = $range;

        $this->io->text(sprintf('Retrieving orders fulfilled between %s and %s',
            $start->format('Y-m-d'), $end->format('Y-m-d')));

        $orders = $this->orderRepository->findFulfilledOrdersByDateRange($start, $end);
    }

    private function getDateRange(InputInterface $input)
    {
        $dateOption = $input->getOption('month');

        if ($dateOption) {

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

        $start = new \DateTime();
        $start->setDate($start->format('Y'), $start->format('m'), 1);
        $start->setTime(0, 0, 0);

        $end = clone $start;
        $end->setDate($start->format('Y'), $start->format('m'), $start->format('t'));
        $end->setTime(23, 59, 59);

        return [ $start, $end ];
    }
}
