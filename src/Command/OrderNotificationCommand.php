<?php

namespace AppBundle\Command;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Reactor\PublishToRedis;
use AppBundle\Domain\Order\Reactor\SendRemotePushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\SocketIoManager;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrderNotificationCommand extends Command
{
    public function __construct(
        RepositoryInterface $orderRepository,
        RemotePushNotificationManager $remotePushNotificationManager,
        SocketIoManager $socketIoManager,
        UserManagerInterface $userManager,
        PublishToRedis $websocket,
        SendRemotePushNotification $push)
    {
        $this->orderRepository = $orderRepository;
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->socketIoManager = $socketIoManager;
        $this->userManager = $userManager;

        $this->websocket = $websocket;
        $this->push = $push;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:notifications:order')
            ->setDescription('Send a notification about a created order')
            ->addOption(
                'order',
                'o',
                InputOption::VALUE_REQUIRED,
                'Order id'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $order = $this->orderRepository->find($input->getOption('order'));

        if (null === $order) {
            return 1;
        }

        $event = new OrderCreated($order);

        call_user_func_array($this->websocket, [ $event ]);
        call_user_func_array($this->push, [ $event ]);

        return 0;
    }
}
