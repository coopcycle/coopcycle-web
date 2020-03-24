<?php

namespace AppBundle\Command;

use AppBundle\Entity\Task;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\PushNotification;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GeofencingCommand extends Command
{
    private $doctrine;
    private $tile38;
    private $messageBus;
    private $translator;
    private $logger;

    private $io;

    public function __construct(
        EntityManagerInterface $doctrine,
        Redis $tile38,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->tile38 = $tile38;
        $this->messageBus = $messageBus;
        $this->translator = $translator;
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
        $taskRepository = $this->doctrine->getRepository(Task::class);
        $orderRepository = $this->doctrine->getRepository(Order::class);

        $limit = (int) $input->getOption('limit');
        $messageCount = 0;

        $this->logMessage(sprintf('Consumer will process %d messages', $limit));

        // @see https://github.com/nrk/predis/blob/v1.1/examples/pubsub_consumer.php

        $pubsub = $this->tile38->pubSubLoop();

        $pubsub->psubscribe('coopcycle:dropoff:*');

        foreach ($pubsub as $message) {

            switch ($message->kind) {

                case 'psubscribe':
                    $this->logMessage(sprintf('Subscribed to channels matching "%s"', $message->channel));
                    break;

                case 'pmessage':

                    // (
                    //     [kind] => pmessage
                    //     [channel] => coopcycle:dropoff:7395
                    //     [payload] => {
                    //         "command":"set",
                    //         "group":"5e78da00fdee2e0001356871",
                    //         "detect":"enter",
                    //         "hook":"coopcycle:dropoff:7395",
                    //         "key":"coopcycle:fleet",
                    //         "time":"2020-03-23T15:47:12.1482893Z",
                    //         "id":"bot_2",
                    //         "object":{"type":"Point","coordinates":[2.3184081,48.8554067]}
                    //     }
                    // )

                    $this->logMessage(sprintf('Received pmessage on channel "%s"', $message->channel));

                    $payload = json_decode($message->payload, true);

                    preg_match('/^coopcycle:dropoff:([0-9]+)$/', $payload['hook'], $matches);

                    $taskId = (int) $matches[1];

                    $task = $taskRepository->find($taskId);

                    // This is not the assigned messenger
                    if ($task->getAssignedCourier()->getUsername() !== $payload['id']) {
                        break;
                    }

                    // There is no associated order
                    if (!$order = $orderRepository->findOneByTask($task)) {
                        break;
                    }

                    $customer = $order->getCustomer();

                    $this->logMessage(sprintf('Sending push notification to "%s"', $customer->getUsername()));

                    $notificationTitle = $this->translator->trans('notifications.messenger_approaching', [
                        '%customer%' => $customer->getUsername(),
                        '%messenger%' => $task->getAssignedCourier()->getUsername(),
                    ]);

                    $this->messageBus->dispatch(
                        new PushNotification($notificationTitle, [ $customer->getUsername() ])
                    );

                    // TODO Send notification/SMS

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
