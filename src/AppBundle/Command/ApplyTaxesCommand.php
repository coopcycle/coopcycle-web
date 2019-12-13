<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use Doctrine\Common\Persistence\ObjectManager;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ApplyTaxesCommand extends Command
{
    private $orderTaxesProcessor;
    private $orderRepository;
    private $orderManager;

    const ORDERS_PER_PAGE = 15;

    public function __construct(
        OrderTaxesProcessor $orderTaxesProcessor,
        RepositoryInterface $orderRepository,
        ObjectManager $orderManager)
    {
        $this->orderTaxesProcessor = $orderTaxesProcessor;
        $this->orderRepository = $orderRepository;
        $this->orderManager = $orderManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:process-taxes')
            ->setDescription('Process order taxes');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->title('Processing order taxes');

        $qb = $this->orderRepository->createQueryBuilder('o')
            ->andWhere('o.state != :state')
            ->setParameter('state', OrderInterface::STATE_CART);

        $count = (clone $qb)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();

        $this->io->section(sprintf('Found %d orders to process', $count));

        $qb->setMaxResults(self::ORDERS_PER_PAGE);

        $pages = ceil($count / self::ORDERS_PER_PAGE);

        for ($p = 1; $p <= $pages; $p++) {

            $offset = ($p - 1) * self::ORDERS_PER_PAGE;

            $orders = $qb
                ->setFirstResult($offset)
                ->getQuery()
                ->getResult();

            foreach ($orders as $order) {
                $this->io->text(sprintf('Processing taxes on order #%d', $order->getId()));
                $this->orderTaxesProcessor->process($order);
            }
            $this->orderManager->flush();
            $this->orderManager->clear();
        }

        return 0;
    }
}
