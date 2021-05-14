<?php

namespace AppBundle\Service;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Domain\Order\Event as OrderEvent;
use AppBundle\Domain\HumanReadableEventInterface;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Entity\User;
use AppBundle\Message\TopBarNotification;
use AppBundle\Sylius\Order\OrderInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use phpcent\Client as CentrifugoClient;
use Redis;
use Ramsey\Uuid\Uuid;
use SimpleBus\Message\Name\NamedMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LiveUpdates
{
    use TokenStorageTrait;

    private $redis;
    private $userManager;
    private $serializer;
    private $translator;
    private $centrifugoClient;
    private $messageBus;
    private $namespace;

    public function __construct(
        Redis $redis,
        UserManagerInterface $userManager,
        TokenStorageInterface $tokenStorage,
        SerializerInterface $serializer,
        TranslatorInterface $translator,
        CentrifugoClient $centrifugoClient,
        MessageBusInterface $messageBus,
        string $namespace)
    {
        $this->redis = $redis;
        $this->userManager = $userManager;
        $this->tokenStorage = $tokenStorage;
        $this->serializer = $serializer;
        $this->translator = $translator;
        $this->centrifugoClient = $centrifugoClient;
        $this->messageBus = $messageBus;
        $this->namespace = $namespace;
    }

    public function toAdmins($message, array $data = [])
    {
        $admins = $this->userManager->findUsersByRole('ROLE_ADMIN');

        $this->toUsers($admins, $message, $data);
    }

    public function toUserAndAdmins(UserInterface $user, $message, array $data = [])
    {
        $users = $this->userManager->findUsersByRole('ROLE_ADMIN');

        // If the user is also an admin, don't notify twice
        if (!in_array($user, $users, true)) {
            $users[] = $user;
        }

        $this->toUsers($users, $message, $data);
    }

    public function toOrderWatchers(OrderInterface $order, $message, array $data = [])
    {
        $messageName = $message instanceof NamedMessage ? $message::messageName() : $message;

        if ($message instanceof SerializableEventInterface && empty($data)) {
            $data = $message->normalize($this->serializer);
        }

        $payload = [
            'name' => $messageName,
            'data' => $data
        ];

        $this->centrifugoClient->publish(
            $this->getOrderChannelName($order),
            ['event' => $payload]
        );
    }

    /**
     * @param UserInterface[] $users
     * @param NamedMessage|string $message
     * @param array $data
     */
    public function toUsers($users, $message, array $data = [])
    {
        $messageName = $message instanceof NamedMessage ? $message::messageName() : $message;

        if ($message instanceof SerializableEventInterface && empty($data)) {
            $data = $message->normalize($this->serializer);
        }

        $payload = [
            'name' => $messageName,
            'data' => $data
        ];

        //
        // Redis (legacy)
        //

        $redisChannels = array_map(function (UserInterface $user) {
            return sprintf('users:%s', $user->getUsername());
        }, $users);

        foreach ($redisChannels as $channel) {
            $this->redis->publish($channel, json_encode($payload));
        }

        //
        // Centrifugo
        //

        $centrifugoChannels = array_map(function (UserInterface $user) {
            return $this->getEventsChannelName($user);
        }, $users);

        // We use broadcast to reduce the number of HTTP requests
        $this->centrifugoClient->broadcast(
            $centrifugoChannels,
            ['event' => $payload]
        );

        $this->createNotification($users, $message);
    }

    /**
     * @param UserInterface[] $users
     * @param mixed $message
     */
    private function createNotification($users, $message)
    {
        // Since we use Centrifugo the execution time to publish events has increased.
        // This is because for each event, it needs to send 3 HTTP requests.
        // To improve performance, we manage top bar notifications via an async job.
        if ($message instanceof HumanReadableEventInterface) {

            $usernames = array_map(function (UserInterface $user) {
                return $user->getUsername();
            }, $users);

            $text = $message->forHumans($this->translator, $this->getUser());

            $this->messageBus->dispatch(
                new TopBarNotification($usernames, $text)
            );
        }
    }

    /**
     * @param UserInterface|string $user
     *
     * @return string
     */
    private function getEventsChannelName($user)
    {
        $username = $user instanceof UserInterface ? $user->getUsername() : $user;

        return sprintf('%s_events#%s', $this->namespace, $username);
    }

    private function getOrderChannelName(OrderInterface $order)
    {
        return sprintf('%s_order_events#%d', $this->namespace, $order->getId());
    }

    /**
     * @param UserInterface|string $user
     * @param array $payload
     */
    public function publishEvent($user, array $payload)
    {
        $channel = $this->getEventsChannelName($user);

        $this->centrifugoClient->publish($channel, ['event' => $payload]);
    }
}
