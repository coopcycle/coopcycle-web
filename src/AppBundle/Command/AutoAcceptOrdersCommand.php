<?php

namespace AppBundle\Command;

use AppBundle\Entity\Order;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AutoAcceptOrdersCommand extends ContainerAwareCommand
{
    private $doctrine;
    private $orderRepository;
    private $orderManager;
    private $logger;

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:accept')
            ->setDescription('Accept all orders.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->doctrine = $this->getContainer()->get('doctrine');
        $this->orderRepository = $this->doctrine->getRepository(Order::class);
        $this->orderManager = $this->getContainer()->get('order.manager');
        $this->logger = $this->getContainer()->get('logger');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orders = $this->orderRepository->findBy([
            'status' => Order::STATUS_WAITING
        ]);

        $output->writeln(sprintf('Found %s order(s) with status WAITING', count($orders)));

        foreach ($orders as $order) {
            $output->writeln(sprintf('Accepting order #%d', $order->getId()));
            try {
                $this->orderManager->accept($order);
                $this->doctrine->getManagerForClass(Order::class)->flush();
            } catch (\Exception $e) {
                $output->writeln(sprintf('Could not accept order #%d', $order->getId()));
                $this->logger->error($e->getMessage());
            }
        }
    }
}
