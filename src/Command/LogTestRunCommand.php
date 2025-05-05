<?php

namespace AppBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LogTestRunCommand extends Command
{
    public function __construct(
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:tests:log')
            ->setDescription('Log a test run')
            ->addOption(
                'message',
                'm',
                InputOption::VALUE_REQUIRED,
                'The date to mock.'
            );
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ('test' !== $this->environment) {
            $this->logger->error('This command can only be executed in test environment');
            return 1;
        }

        $message = $input->getOption('message');

        if (null === $message) {
            $this->logger->error('No message provided');
            return 1;
        }

        $this->logger->info('TestRun: ' . $message);

        return 0;
    }
}
