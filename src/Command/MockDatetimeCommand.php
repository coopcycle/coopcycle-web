<?php

namespace AppBundle\Command;

use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MockDatetimeCommand extends Command
{
    private ?StyleInterface $io;

    public function __construct(
        private readonly Redis $redis,
        private readonly string $environment,
        private readonly LoggerInterface $logger)
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
            );
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
        if ('test' !== $this->environment) {
            $this->io->error('This command can only be executed in test environment');
            return 1;
        }

        $datetime = $input->getOption('datetime');

        if (null === $datetime) {
            $this->io->error('No date provided');
            return 1;
        }

        $this->logger->info('Mocking date: ' . $datetime);

        $this->redis->set('datetime:now', Carbon::parse($datetime)->toAtomString());

        return 0;
    }
}
