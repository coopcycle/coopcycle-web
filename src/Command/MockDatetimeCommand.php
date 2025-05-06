<?php

namespace AppBundle\Command;

use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MockDatetimeCommand extends Command
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:datetime:mock')
            ->setDescription('Mock the current date')
            ->addOption(
                'datetime',
                'd',
                InputOption::VALUE_REQUIRED,
                'The date to mock.'
            )
            ->addOption(
                'reset',
                'r',
                InputOption::VALUE_NONE,
                'Reset the mock.'
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

        $reset = $input->getOption('reset');

        if (false !== $reset) {
            // in this case, the option was passed when running the command

            $this->logger->info('Resetting date mock');

            $this->redis->del('datetime:now');

            return 0;
        }

        $datetime = $input->getOption('datetime');

        if (null === $datetime) {
            $this->logger->error('No date provided');
            return 1;
        }

        $this->logger->info('Mocking date: ' . $datetime);

        $this->redis->set('datetime:now', Carbon::parse($datetime)->toAtomString());

        return 0;
    }
}
