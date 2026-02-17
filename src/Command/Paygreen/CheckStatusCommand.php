<?php

namespace AppBundle\Command\Paygreen;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\PaygreenManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Paygreen\Sdk\Payment\V3\Client as PaygreenClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckStatusCommand extends Command
{
    /**
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    private $io;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaygreenManager $paygreenManager,
        private PaygreenClient $paygreenClient)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:paygreen:check-status')
            ->setDescription('Check payment status Paygreen orders')
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

        $this->io->text('Loading orders...');

        $qb = $this->entityManager->getRepository(Payment::class)->createQueryBuilder('p');
        $qb->select(
            'o.number',
            'o.createdAt',
            'p.details'
        );
        $qb->innerJoin(Order::class, 'o', Join::WITH, 'p.order = o.id');

        $qb->andWhere('o.state = :fulfilled')->setParameter('fulfilled', Order::STATE_FULFILLED);
        $qb->andWhere('p.state = :completed')->setParameter('completed', Payment::STATE_COMPLETED);
        $qb->andWhere('RIGHT_EXISTS_ON_LEFT(CAST(p.details AS jsonb), \'paygreen_payment_order_id\') = TRUE');

        $qb->andWhere('o.createdAt BETWEEN :start AND :end');
        $qb->setParameter('start', new \DateTime($start));
        $qb->setParameter('end', new \DateTime($end));

        $qb->orderBy('o.createdAt', 'DESC');

        $table = new Table($output);
        $table->setHeaders(['Created at', 'Number', 'PayGreen status', 'Stripe Payment Intent']);

        $rows = $qb->getQuery()->getArrayResult();

        foreach ($rows as $row) {

            $paymentOrderId = $row['details']['paygreen_payment_order_id'];

            $response = $this->paygreenClient->getPaymentOrder($paymentOrderId);
            $data = json_decode($response->getBody()->getContents(), true);

            $paymentOrder = $data['data'];

            if ($paymentOrder['status'] === 'payment_order.successed') {
                continue;
            }

            $table->addRow([
                $row['createdAt']->format(\DateTime::ATOM),
                $row['number'],
                $paymentOrder['status'],
                $row['details']['payment_intent'] ?? ''
            ]);

        }

        $table->render();

        return 0;
    }
}
