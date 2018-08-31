<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\RestaurantReminder;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrderReminderCommand extends ContainerAwareCommand
{
    const MINUTES_BEFORE_PREPARATION = 15;

    private $orderRepository;
    private $restaurantReminderRepository;
    private $emailManager;
    private $io;
    private $dryRun;

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:reminders')
            ->setDescription('Produce or consume restaurant reminders')
            ->addOption(
                'produce',
                null,
                InputOption::VALUE_NONE,
                'Execute the command in producer mode.'
            )
            ->addOption(
                'consume',
                null,
                InputOption::VALUE_NONE,
                'Execute the command in consumer mode.'
            )
            ->addOption(
                'now',
                null,
                InputOption::VALUE_REQUIRED,
                'Simulate time.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Execute the command as a dry run.'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');

        $this->restaurantReminderRepository = $doctrine->getRepository(RestaurantReminder::class);
        $this->em = $doctrine->getManagerForClass(RestaurantReminder::class);
        $this->orderRepository = $this->getContainer()->get('sylius.repository.order');

        $this->emailManager = $this->getContainer()->get('coopcycle.email_manager');
        $this->remotePushNotificationManager =
            $this->getContainer()->get('coopcycle.remote_push_notification_manager');

        $this->io = new SymfonyStyle($input, $output);
        $this->dryRun = $input->getOption('dry-run');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = $input->getOption('now');
        if ($now !== null) {
            $now = new \DateTime($now);
        } else {
            $now = new \DateTime();
        }

        // $dryRun = $input->getOption('dry-run');
        // $produce = $input->getOption('produce');
        // $consume = $input->getOption('consume');

        // TODO Make sure both options are NOT present

        $this->io->text(sprintf('Current time is %s', $now->format('Y-m-d H:i:s')));

        $this->io->title('Running in producer mode');
        $this->produce($now);

        $this->io->title('Running in consumer mode');
        $this->consume($now);

        if ($this->dryRun) {
            $this->io->warning('No action performed');
        }

        // $ordersToCancel = array_filter($orders, function (OrderInterface $order) use ($now) {
        //     $preparationExpectedAt = $order->getPreparationExpectedAt();
        //     if (null === $preparationExpectedAt) {
        //         return false;
        //     }

        //     return $preparationExpectedAt < $now;
        // });

        // $this->io->text(sprintf('Found %d order(s) to cancel', count($ordersToCancel)));
        // if (count($ordersToCancel) > 0) {
        //     $this->io->listing(array_map(function (OrderInterface $order) {
        //         return sprintf('#%d (should have started %s)',
        //             $order->getId(),
        //             Carbon::instance($order->getPreparationExpectedAt())->diffForHumans()
        //         );
        //     }, $ordersToCancel));
        // }

        // /* Processing */

        // if (count($ordersToCancel) > 0) {
        //     $this->io->section('Cancelling orders');
        //     $this->io->progressStart(count($ordersToCancel));
        //     foreach ($ordersToCancel as $order) {
        //         if (!$dryRun) {
        //             $this->orderManager->cancel($order);
        //             $this->em->flush();
        //         }
        //         $this->io->progressAdvance();
        //     }
        //     $this->io->progressFinish();
        // }

        //     // $this->io->section('Cancelling orders');
        //     // $this->io->progressStart(count($ordersToCancel));
        //     // foreach ($ordersToCancel as $order) {
        //     //     if (!$dryRun) {
        //     //         $this->orderManager->cancel($order);
        //     //         $this->em->flush();
        //     //     }
        //     //     $this->io->progressAdvance();
        //     // }
        //     // $this->io->progressFinish();
        // }
    }

    private function produce(\DateTime $now)
    {
        // TODO Select only orders with restaurant != NULL
        $orders = $this->orderRepository->findBy(
            ['state' => OrderInterface::STATE_NEW],
            ['createdAt' => 'ASC']
        );

        $orders = array_filter($orders, function (OrderInterface $order) use ($now) {
            $preparationExpectedAt = $order->getPreparationExpectedAt();
            if (null === $preparationExpectedAt) {
                return false;
            }

            return $preparationExpectedAt > $now;
        });

        $this->io->text(sprintf('Found %d order(s) waiting to be accepted', count($orders)));
        if (count($orders) > 0) {
            $this->io->listing(array_map(function (OrderInterface $order) {
                return sprintf('#%d (should start in %s)',
                    $order->getId(),
                    Carbon::instance($order->getPreparationExpectedAt())->diffForHumans()
                );
            }, $orders));
        }

        $reminders = [];
        foreach ($orders as $order) {

            $qb = $this->restaurantReminderRepository
                ->createQueryBuilder('r')
                ->andWhere('r.order = :order')
                ->andWhere('r.restaurant = :restaurant')
                ->setParameter('order', $order)
                ->setParameter('restaurant', $order->getRestaurant());

            $reminder = $qb->getQuery()->getOneOrNullResult();

            if (null === $reminder) {

                $scheduledAt = clone $order->getPreparationExpectedAt();
                $scheduledAt->modify(sprintf('-%d minutes', self::MINUTES_BEFORE_PREPARATION));

                $reminder = new RestaurantReminder($order->getRestaurant(), $order);
                $reminder->setState('scheduled');
                $reminder->setScheduledAt($scheduledAt);
                $reminder->setExpiredAt($order->getPreparationExpectedAt());

                $this->em->persist($reminder);
            }

            $reminders[] = $reminder;
        }

        $this->io->text(sprintf('Processed %d reminder(s)', count($reminders)));
        $this->io->listing(array_map(function (RestaurantReminder $reminder) {
            if ($reminder->getId() === null) {
                return sprintf('#%d (scheduling new reminder at %s)',
                    $reminder->getOrder()->getId(),
                    $reminder->getScheduledAt()->format('H:i')
                );
            } else {
                return sprintf('#%d (reminder scheduled at %s)',
                    $reminder->getOrder()->getId(),
                    $reminder->getScheduledAt()->format('H:i')
                );
            }
        }, $reminders));

        if (!$this->dryRun) {
            $this->em->flush();
        }
    }

    private function consume(\DateTime $now)
    {
        $qb = $this->restaurantReminderRepository
            ->createQueryBuilder('r')
            ->andWhere('r.state = :state')
            // ->andWhere('r.scheduledAt <= :now')
            // ->andWhere('r.expiredAt > :now')
            // ->setParameter('now', $now)
            ->setParameter('state', 'scheduled');

        $reminders = $qb->getQuery()->getResult();

        $this->io->text(sprintf('Found %d reminders(s) scheduled', count($reminders)));
        $this->io->listing(array_map(function (RestaurantReminder $reminder) {
            return sprintf('#%d (reminder scheduled at %s)',
                $reminder->getId(),
                $reminder->getScheduledAt()->format('H:i')
            );
        }, $reminders));

        foreach ($reminders as $reminder) {

            // Reminder is expired
            if ($reminder->isExpired($now)) {
                $this->io->text(sprintf('Reminder #%d is expired', $reminder->getId()));
                $reminder->setState('expired');
                continue;
            }

            // TODO If order has been accepted or refused, cancel reminder

            if ($reminder->getScheduledAt() <= $now) {
                $this->sendEmail($reminder, $this->io);
                $this->sendNotification($reminder, $this->io);
            }
        }

        if (!$this->dryRun) {
            $this->em->flush();
        }
    }

    private function sendEmail(RestaurantReminder $reminder)
    {
        $owners = $reminder->getRestaurant()->getOwners()->toArray();
        if (count($owners) === 0) {
            // FIXME
        }

        foreach ($owners as $owner) {

            $this->io->text(sprintf('Sending reminder to user %s', $owner->getUsername()));
            $message = $this->emailManager->createOrderReminderMessage($reminder->getOrder());

            if (!$this->dryRun) {
                $this->emailManager->sendTo($message, $owner->getEmail());
            }
        }

        $reminder->setState('sent');
    }

    private function sendNotification(RestaurantReminder $reminder)
    {
        $data = [
            'order' => $reminder->getOrder()->getId()
        ];

        if (!$this->dryRun) {
            $this->remotePushNotificationManager
                ->send(
                    sprintf('Order #%d is approaching', $reminder->getOrder()->getId()),
                    $reminder->getRestaurant()->getOwners(),
                    $data
                );
        }
    }
}
