<?php

namespace AppBundle\Command;

use AppBundle\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PushNotificationCommand extends ContainerAwareCommand
{
    private $notificationManager;
    private $userManager;

    protected function configure()
    {
        $this
            ->setName('coopcycle:notifications:push')
            ->setDescription('Push a notification to a user.')
            ->addArgument('username', InputArgument::REQUIRED, 'The username.')
            ->addArgument('message', InputArgument::REQUIRED, 'The message.');;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->notificationManager = $this->getContainer()->get('coopcycle.notification_manager');
        $this->userManager = $this->getContainer()->get('fos_user.user_manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $message = $input->getArgument('message');

        $user = $this->userManager->findUserByUsername($username);

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);

        $this->notificationManager->push($notification);
    }
}
