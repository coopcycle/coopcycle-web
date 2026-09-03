<?php

namespace AppBundle\Command;

use AppBundle\Entity\LocalBusiness;
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
    public $io;
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
                'Only process orders created since this date (default: all orders)'
            )
            ->addOption(
                'restaurant',
                null,
                InputOption::VALUE_REQUIRED,
                'Only process orders for this restaurant id'
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
        $since = null !== $since ? new \DateTime($since) : null;

        $restaurantId = $input->getOption('restaurant');
        $restaurant = null;
        if (null !== $restaurantId) {
            $restaurant = $this->orderManager->getRepository(LocalBusiness::class)->find($restaurantId);
            if (null === $restaurant) {
                $this->io->error(sprintf('Restaurant #%d not found', $restaurantId));

                return 1;
            }
        }

        $this->io->title(sprintf(
            'Applying order taxes to orders%s%s',
            null !== $since ? sprintf(' since %s', $since->format(\DateTime::ATOM)) : '',
            null !== $restaurant ? sprintf(' for restaurant "%s" (#%d)', $restaurant->getName(), $restaurant->getId()) : ''
        ));

        $qb = $this->orderRepository->createQueryBuilder('o');

        if (null !== $since) {
            $qb
                ->andWhere('o.createdAt >= :since')
                ->setParameter('since', $since);
        }

        if (null !== $restaurant) {
            // Order has no direct restaurant field: it is derived from the vendors
            // collection (see AppBundle\Entity\Sylius\Order::getRestaurant()).
            // Bind the id rather than the entity: $restaurant is detached by the
            // orderManager->clear() call below on every page after the first.
            $qb
                ->innerJoin('o.vendors', 'v')
                ->andWhere('v.restaurant = :restaurantId')
                ->setParameter('restaurantId', $restaurant->getId());
        }

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
