<?php

namespace AppBundle\Command;

use AppBundle\Service\SettingsManager;
use AppBundle\Service\SmsManager;
use FOS\UserBundle\Model\UserManagerInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mailjet\Client as MailjetClient;
use Mailjet\Resources as MailjetResources;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SmsCommand extends Command
{
    public function __construct(
        SettingsManager $settingsManager,
        UserManagerInterface $userManager,
        SmsManager $smsManager,
        PhoneNumberUtil $phoneNumberUtil)
    {
        $this->settingsManager = $settingsManager;
        $this->userManager = $userManager;
        $this->smsManager = $smsManager;
        $this->phoneNumberUtil = $phoneNumberUtil;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:notifications:sms')
            ->setDescription('Send a SMS notification')
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'User(s) to notify'
            )
            ->addOption(
                'text',
                't',
                InputOption::VALUE_REQUIRED,
                'SMS text',
                'Hello from CoopCycle'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $enabled = $this->settingsManager->get('sms_enabled');

        if (!$enabled) {
            $this->io->caution('SMS sending is not enabled');
            return 1;
        }

        $users = [];
        foreach ($input->getOption('user') as $username) {
            if ($user = $this->userManager->findUserByUsernameOrEmail($username)) {
                if ($telephone = $user->getTelephone()) {
                    $telephone = $this->phoneNumberUtil->format($telephone, PhoneNumberFormat::E164);
                    $this->smsManager->send($input->getOption('text'), $telephone);
                }
            }
        }

        return 0;
    }
}
