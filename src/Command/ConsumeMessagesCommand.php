<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Command to consume messages from the async transport in test environment.
 *
 * This command is useful for testing async message handlers in both PHPUnit tests
 * and e2e tests (Cypress, Behat, etc.). It processes all queued messages from the
 * in-memory transport until the time limit is reached.
 *
 * Usage examples:
 * - From PHPUnit: Use TestUtils::consumeMessages() helper
 * - From e2e tests: Use consumeMessages() helper from e2e/support/commands.js
 * - Direct CLI: php bin/console coopcycle:messenger:consume-test --time-limit=10
 */
class ConsumeMessagesCommand extends Command
{
    public function __construct(
        private readonly string $environment,
        private readonly MessageBusInterface $bus,
        private readonly ServiceProviderInterface $receiverLocator,
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:messenger:consume-test')
            ->setDescription('Consume messages from async transport (test environment only)')
            ->addOption(
                'time-limit',
                't',
                InputOption::VALUE_REQUIRED,
                'Time limit in seconds',
                5
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('test' !== $this->environment) {
            $io->error('This command can only be executed in test environment');
            return Command::FAILURE;
        }

        $timeLimit = (int) $input->getOption('time-limit');

        $io->info(sprintf('Consuming messages from async transport (time limit: %d seconds)...', $timeLimit));

        // Get the async transport ('messenger.transport.async') from the receiver locator
        $transport = $this->receiverLocator->get('async');

        // Create an event dispatcher with a listener to stop after time limit
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit));

        // Create and run the worker
        $worker = new Worker([$transport], $this->bus, $eventDispatcher);
        $worker->run([
            'sleep' => 0, // Don't sleep between messages
        ]);

        $io->success('Finished consuming messages');

        return Command::SUCCESS;
    }
}
