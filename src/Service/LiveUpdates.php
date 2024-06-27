<?php

namespace AppBundle\Service;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Domain\HumanReadableEventInterface;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Domain\SilentEventInterface;
use AppBundle\Message\TopBarNotification;
use AppBundle\Security\UserManager;
use AppBundle\Service\NotificationPreferences;
use AppBundle\Sylius\Order\OrderInterface;
use phpcent\Client as CentrifugoClient;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Name\NamedMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LiveUpdates
{
    use TokenStorageTrait;

    protected $tokenStorage;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        private UserManager $userManager,
        private SerializerInterface $serializer,
        private TranslatorInterface $translator,
        private CentrifugoClient $centrifugoClient,
        private MessageBusInterface $messageBus,
        private NotificationPreferences $notificationPreferences,
        private LoggerInterface $realTimeMessageLogger,
        private string $namespace)
    {
        $this->tokenStorage = $tokenStorage;
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

        $channel = $this->getOrderChannelName($order);

        $this->realTimeMessageLogger->info(sprintf("Publishing event '%s' on channel %s",
            $payload['name'],
            $channel));

        $this->centrifugoClient->publish(
            $channel,
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
        // Centrifugo
        //

        $centrifugoChannels = array_map(function (UserInterface $user) {
            return $this->getEventsChannelName($user);
        }, $users);

        $this->realTimeMessageLogger->info(sprintf("Broadcasting event '%s' on channels %s for users %s",
            $payload['name'],
            implode(', ', $centrifugoChannels),
            implode(', ', array_map(function (UserInterface $user) {
                return $user->getUserIdentifier();
            }, $users))));

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
        $messageName = $message instanceof NamedMessage ? $message::messageName() : $message;

        if ($message instanceof SilentEventInterface) {
            return;
        }

        if (!$this->shouldNotifyEvent($messageName)) {
            return;
        }

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

        $this->realTimeMessageLogger->info(sprintf("Publishing event '%s' on channel %s for user %s",
            $payload['name'],
            $channel,
            $user instanceof UserInterface ? $user->getUserIdentifier() : $user));

        $this->centrifugoClient->publish($channel, ['event' => $payload]);
    }

    private function shouldNotifyEvent(string $messageName)
    {
        return $this->notificationPreferences->isEventEnabled($messageName);
    }
}
