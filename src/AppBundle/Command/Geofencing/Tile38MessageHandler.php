<?php

namespace AppBundle\Command\Geofencing;

use AppBundle\Entity\Task;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\PushNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Tile38MessageHandler
{
    private $namespace;
    private $entityManager;
    private $messageBus;

    public function __construct(
        string $namespace,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator)
    {
        $this->namespace = $namespace;
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->translator = $translator;
    }

    public function __invoke($message)
    {
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

        $taskRepository = $this->entityManager->getRepository(Task::class);
        $orderRepository = $this->entityManager->getRepository(Order::class);

        $payload = json_decode($message->payload, true);

        $regexp = sprintf('/^%s:dropoff:([0-9]+)$/', $this->namespace);

        preg_match($regexp, $payload['hook'], $matches);

        $taskId = (int) $matches[1];

        $task = $taskRepository->find($taskId);

        // This is not the assigned messenger
        if ($task->getAssignedCourier()->getUsername() !== $payload['id']) {
            return;
        }

        // TODO Send SMS

        // There is no associated order
        if (!$order = $orderRepository->findOneByTask($task)) {
            return;
        }

        $customer = $order->getCustomer();

        $notificationTitle = $this->translator->trans('notifications.messenger_approaching', [
            '%customer%' => $customer->getUsername(),
            '%messenger%' => $task->getAssignedCourier()->getUsername(),
        ]);

        $this->messageBus->dispatch(
            new PushNotification($notificationTitle, [ $customer->getUsername() ])
        );
    }
}
