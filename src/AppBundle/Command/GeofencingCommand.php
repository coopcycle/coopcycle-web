<?php

namespace AppBundle\Command;

use AppBundle\Command\Geofencing\Tile38MessageHandler;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GeofencingCommand extends Command
{
    private $tile38;
    private $namespace;
    private $messageHandler;
    private $logger;
    private $io;

    public function __construct(
        Redis $tile38,
        string $namespace,
        Tile38MessageHandler $messageHandler,
        LoggerInterface $logger)
    {
        $this->tile38 = $tile38;
        $this->namespace = $namespace;
        $this->messageHandler = $messageHandler;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:geofencing')
            ->setDescription('Subscribe to Tile38 geofencing channels')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit the number of processed messages',
                '10'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = (int) $input->getOption('limit');
        $messageCount = 0;

        $this->logMessage(sprintf('Consumer will process %d messages', $limit));

        // @see https://github.com/nrk/predis/blob/v1.1/examples/pubsub_consumer.php

        $pubsub = $this->tile38->pubSubLoop();

        $pubsub->psubscribe(sprintf('%s:dropoff:*', $this->namespace));

        foreach ($pubsub as $message) {

            switch ($message->kind) {

                case 'psubscribe':
                    $this->logMessage(sprintf('Subscribed to channels matching "%s"', $message->channel));
                    break;

                case 'pmessage':
                    $this->logMessage(sprintf('Received pmessage on channel "%s"', $message->channel));

                    call_user_func_array($this->messageHandler, [ $message ]);

                    ++$messageCount;

                    if ($messageCount >= $limit) {
                        $this->logMessage(sprintf('Unsubscribing after processing %d messages', $messageCount));
                        $pubsub->punsubscribe('coopcycle:dropoff:*');
                    }

                    break;
            }
        }

        // Always unset the pubsub consumer instance when you are done! The
        // class destructor will take care of cleanups and prevent protocol
        // desynchronizations between the client and the server.
        unset($pubsub);

        $this->logMessage('Consumer exited');

        return 0;
    }

    private function logMessage($message)
    {
        $this->logger->info($message);
        $this->io->text($message);
    }
}
