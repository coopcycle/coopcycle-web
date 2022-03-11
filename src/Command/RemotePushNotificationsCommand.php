<?php

namespace AppBundle\Command;

use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RemotePushNotificationsCommand extends Command
{
    public function __construct(
        RemotePushNotificationManager $remotePushNotificationManager,
        UserManagerInterface $userManager)
    {
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->userManager = $userManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:notifications:remote-push')
            ->setDescription('Send a remote push notification')
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'User(s) to notify'
            )
            ->addOption(
                'title',
                't',
                InputOption::VALUE_REQUIRED,
                'Notification title',
                'Hello from CoopCycle'
            )
            ->addOption(
                'data',
                'd',
                InputOption::VALUE_REQUIRED,
                'Notification data',
                '{}'
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
        $title = $input->getOption('title');
        $data = json_decode($input->getOption('data'), true);

        $users = [];
        foreach ($input->getOption('user') as $username) {
            if ($user = $this->userManager->findUserByUsernameOrEmail($username)) {
                $this->io->text(sprintf('Sending notification to %s', $username));
                $users[] = $user;
            }
        }

        $this->remotePushNotificationManager->send($title, $users, $data);

        return 0;
    }
}
