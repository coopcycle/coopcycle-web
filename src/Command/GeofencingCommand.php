<?php

namespace AppBundle\Command;

use AppBundle\Entity\Task;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\PushNotification;
use Doctrine\ORM\EntityManagerInterface;
use Redis;
use RedisException;
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
    private $messageCount = 0;

    public function __construct(
        EntityManagerInterface $doctrine,
        Redis $tile38,
        string $doorstepChanNamespace,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->tile38 = $tile38;
        $this->doorstepChanNamespace = $doorstepChanNamespace;
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
            )
            ->addOption(
                'stop',
                's',
                InputOption::VALUE_NONE,
                'Unsubscribe from Tile38',
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopChannel = sprintf('%s:geofencing:stop', $this->doorstepChanNamespace);

        if (true === $input->getOption('stop')) {

            $this->logMessage(sprintf('Sending "stop" message on channel "%s"', $stopChannel));
            $this->tile38->publish($stopChannel, 'stop');

            return 0;
        }

        // @see https://github.com/phpredis/phpredis/issues/1727
        // ini_set('default_socket_timeout', -1);

        $taskRepository = $this->doctrine->getRepository(Task::class);
        $orderRepository = $this->doctrine->getRepository(Order::class);

        $limit = (int) $input->getOption('limit');
        $this->messageCount = 0;

        $this->logMessage(sprintf('Consumer will process %d messages', $limit));

        $pattern = sprintf('%s:dropoff:*', $this->doorstepChanNamespace);

        $this->logMessage(sprintf('Subscribing to channels with pattern %s', $pattern));

        $patterns = [
            $pattern,
            $stopChannel,
        ];

        try {

            $this->tile38->pSubscribe(
                $patterns,
                function(Redis $redis, $pattern, $channel, $message) use ($taskRepository, $orderRepository, $limit, $stopChannel, $patterns) {

                    // {
                    //   "command":"set",
                    //   "group":"5ed36b0268c82400015ee98e",
                    //   "detect":"enter",
                    //   "hook":"coopcycle:dropoff:7428",
                    //   "key":"coopcycle:fleet",
                    //   "time":"2020-05-31T08:29:54.8237726Z",
                    //   "id":"bot_1",
                    //   "object":{
                    //     "type":"Point",
                    //     "coordinates":[
                    //       2.3413644,
                    //       48.8606107,
                    //       123456789
                    //     ]
                    //   }
                    // }

                    $this->logMessage(sprintf('Received pmessage on channel "%s"', $channel));

                    if ($channel === $stopChannel) {
                        $this->logMessage('Unsubscribing after receiving stop signal');
                        $redis->pUnsubscribe($patterns);
                        $redis->close();
                        return;
                    }

                    $payload = json_decode($message, true);

                    $regexp = sprintf('/^%s:dropoff:([0-9]+)$/', $this->doorstepChanNamespace);

                    preg_match($regexp, $payload['hook'], $matches);

                    $taskId = (int) $matches[1];

                    $task = $taskRepository->find($taskId);

                    // This is not the assigned messenger
                    if ($task->getAssignedCourier()->getUsername() !== $payload['id']) {
                        return;
                    }

                    // There is no associated order
                    if (!$order = $orderRepository->findOneByTask($task)) {
                        return;
                    }

                    $customer = $order->getCustomer();

                    if (!$customer->hasUser()) {
                        return;
                    }

                    $this->logMessage(sprintf('Sending push notification to "%s"', $customer->getUser()->getUsername()));

                    $notificationTitle = $this->translator->trans('notifications.messenger_approaching', [
                        '%customer%' => $customer->getUser()->getUsername(),
                        '%messenger%' => $task->getAssignedCourier()->getUsername(),
                    ]);

                    $this->messageBus->dispatch(
                        new PushNotification($notificationTitle, [ $customer->getUsername() ])
                    );

                    // TODO Send notification/SMS

                    $this->messageCount = $this->messageCount + 1;

                    if ($this->messageCount >= $limit) {
                        $this->logMessage(sprintf('Unsubscribing after processing %d messages', $this->messageCount));
                        $redis->pUnsubscribe($patterns);
                        $redis->close();
                    }

                }
            );

        } catch (RedisException $e) {
            $this->logMessage($e->getMessage());
        }

        $this->logMessage('Consumer exited');

        return 0;
    }

    private function logMessage($message)
    {
        $this->logger->info($message);
        $this->io->text($message);
    }
}
