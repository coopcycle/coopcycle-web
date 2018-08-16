<?php

namespace AppBundle\Command;

use AppBundle\Entity\RemotePushToken;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemotePushNotificationCommand extends ContainerAwareCommand
{
    private $notificationManager;
    private $userManager;
    private $remotePushTokenRepository;

    protected function configure()
    {
        $this
            ->setName('coopcycle:remote-notifications:push')
            ->setDescription('Push a remote notification to a user.')
            ->addArgument('username', InputArgument::REQUIRED, 'The username.')
            ->addArgument('message', InputArgument::REQUIRED, 'The message.')
            ->addOption(
                'data',
                'd',
                InputOption::VALUE_REQUIRED,
                'The event data.',
                '{}'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->notificationManager = $this->getContainer()->get('coopcycle.notification_manager');
        $this->userManager = $this->getContainer()->get('fos_user.user_manager');
        $this->remotePushTokenRepository = $this->getContainer()->get('doctrine')->getRepository(RemotePushToken::class);
        $this->remotePushNotificationManager = $this->getContainer()->get('coopcycle.remote_push_notification_manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $message = $input->getArgument('message');
        $data = json_decode($input->getOption('data'), true);

        $user = $this->userManager->findUserByUsername($username);

        $tokens = $this->remotePushTokenRepository->findByUser($user);
        if (count($tokens) === 0) {
            $output->writeln(sprintf('<error>User %s has no remote push tokens configured</error>', $username));
            return;
        }

        foreach ($tokens as $token) {
            $output->writeln(sprintf('<info>Sending remote push notification to platform %s</info>', $token->getPlatform()));
            $this->remotePushNotificationManager->send($message, $token, $data);
        }
    }
}
