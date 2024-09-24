<?php

namespace AppBundle\Command;

use AppBundle\Edenred\Client;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

Class EdenredCommand extends Command
{
    const PAYMENT_METHOD = 'EDENRED';

    public function __construct(
        EntityManagerInterface $entityManager,
        SettingsManager $settingsManager,
        Client $edenred)
    {
        $this->entityManager = $entityManager;
        $this->settingsManager = $settingsManager;
        $this->edenred = $edenred;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:edenred:capture')
            ->setDescription('Captures Edenred transactions.')
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
        $qb = $this->entityManager->getRepository(PaymentInterface::class)
            ->createQueryBuilder('p')
            ->join(PaymentMethodInterface::class, 'pm', Expr\Join::WITH, 'p.method = pm.id')
            ->andWhere('pm.code = :code')
            ->andWhere('p.state = :completed')
            ->andWhere('JSON_GET_FIELD_AS_TEXT(p.details, \'edenred_capture_id\') IS NULL')
            ->setParameter('code', self::PAYMENT_METHOD)
            ->setParameter('completed', PaymentInterface::STATE_COMPLETED)
            ;

        $payments = $qb->getQuery()->getResult();

        foreach ($payments as $payment) {

            $this->io->text(sprintf('Capturing authorization "%s" for payment #%d, order "%s"',
                $payment->getEdenredAuthorizationId(),
                $payment->getId(),
                $payment->getOrder()->getNumber()
            ));

            try {

                $captureId = $this->edenred->captureTransaction($payment);
                $payment->setEdenredCaptureId($captureId);

                $this->io->text(sprintf('Transaction captured with "%s"', $captureId));

                $this->entityManager->flush();

            } catch (\Exception $e) {
                $this->io->caution($e->getMessage());
            }

        }

        return 0;
    }
}
