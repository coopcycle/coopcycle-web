<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use Carbon\Carbon;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WatchExpiringAuthorizationCommand extends Command
{
    private $orderRepository;
    private $stripeManager;
    private $settingsManager;
    private $emailManager;

    public function __construct(
        RepositoryInterface $orderRepository,
        StripeManager $stripeManager,
        SettingsManager $settingsManager,
        EmailManager $emailManager)
    {
        $this->orderRepository = $orderRepository;
        $this->stripeManager = $stripeManager;
        $this->settingsManager = $settingsManager;

        $this->emailManager = $emailManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:watch-expiring-authorization')
            ->setDescription('Watch Stripe authorizations that will expire soon')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Dry run (do not send emails)'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = $input->getOption('dry-run');

        $this->stripeManager->configure();

        $now = Carbon::now();

        $qb = $this->orderRepository->createQueryBuilder('o');

        $qb
            ->andWhere('o.state = :state')
            ->setParameter('state', OrderInterface::STATE_ACCEPTED)
            ->orderBy('o.createdAt', 'DESC')
            ;

        $orders = $qb->getQuery()->getResult();

        foreach ($orders as $order) {

            $this->io->section(sprintf('Processing order #%d', $order->getId()));

            $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);

            if (!$payment) {
                $this->io->text(sprintf('Order #%d has no authorized payment, skipping', $order->getId()));
                continue;
            }

            $stripeOptions = [];

            $stripeUserId = $payment->getStripeUserId();
            if ($stripeUserId) {
                $stripeOptions['stripe_account'] = $stripeUserId;
            }

            try {

                $charge = Stripe\Charge::retrieve($payment->getCharge(), $stripeOptions);

                // Authorization is valid for 7 days
                $created = Carbon::createFromTimestamp($charge->created);
                $expiration = $created->add(7, 'days');

                $diffInDays = $now->diffInDays($expiration, false);

                if ($diffInDays < 0) {
                    $this->io->text(sprintf('Authorization for order #%d is already expired', $order->getId()));
                    continue;
                }

                $this->io->text(sprintf('Authorization for order #%d will expire in %s',
                    $order->getId(), $expiration->diffForHumans()));

                if ($diffInDays > 2) {
                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                $this->io->text('Sending reminder emails…');

                // Send email to admin
                $this->emailManager->sendTo(
                    $this->emailManager->createExpiringAuthorizationReminderMessageForAdmin($order),
                    $this->settingsManager->get('administrator_email')
                );

                // Send email to restaurant owners
                $owners = $order->getRestaurant()->getOwners()->toArray();
                if (count($owners) > 0) {

                    $ownerMails = [];
                    foreach ($owners as $owner) {
                        $ownerMails[$owner->getEmail()] = $owner->getFullName();
                    }

                    $this->emailManager->sendTo(
                        $this->emailManager->createExpiringAuthorizationReminderMessageForOwner($order),
                        $ownerMails
                    );
                }

            } catch (Stripe\Exception\InvalidRequestException $e) {
                $this->io->caution($e->getMessage());
            }
        }

        return 0;
    }
}
