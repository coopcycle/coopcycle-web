<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        EntityManagerInterface $orderManager)
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
            ->setDescription('Process order taxes')
            ->addOption(
                'since',
                null,
                InputOption::VALUE_REQUIRED,
                'Process orders since date'
            );
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
        $since = $input->getOption('since');
        $since = new \DateTime($since);

        $this->io->title(sprintf('Applying order taxes to orders since %s', $since->format(\DateTime::ATOM)));

        $qb = $this->orderRepository->createQueryBuilder('o')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ;

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
                $this->io->text(sprintf('Processing taxes on order #%d (state = "%s", created = "%s")',
                    $order->getId(),
                    $order->getState(),
                    $order->getCreatedAt()->format(\DateTime::ATOM)));
                $this->orderTaxesProcessor->process($order);
            }

            $this->orderManager->flush();
            $this->orderManager->clear();
        }

        return 0;
    }
}
