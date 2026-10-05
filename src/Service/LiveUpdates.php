<?php

namespace AppBundle\Service;

use AppBundle\Domain\HumanReadableEventInterface;
use AppBundle\Domain\NamedMessage;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Domain\SilentEventInterface;
use AppBundle\Message\TopBarNotification;
use AppBundle\Security\UserManager;
use AppBundle\Service\NotificationPreferences;
use AppBundle\Sylius\Order\OrderInterface;
use phpcent\Client as CentrifugoClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LiveUpdates
{
    /**
     * Roles that render the dispatch board, and therefore need every event the
     * board displays. Keep this as the single source of truth: publishing an
     * event to a narrower set makes the board go silently stale for the roles
     * left out, with no error anywhere.
     */
    public const DISPATCH_ROLES = ['ROLE_ADMIN', 'ROLE_DISPATCHER'];

    public function __construct(
        private Security $security,
        private UserManager $userManager,
        private SerializerInterface $serializer,
        private TranslatorInterface $translator,
        private CentrifugoClient $centrifugoClient,
        private MessageBusInterface $messageBus,
        private NotificationPreferences $notificationPreferences,
        private LoggerInterface $realTimeMessageLogger,
        private string $namespace)
    {
    }

    public function toDispatchers($message, array $data = [])
    {
        $this->toRoles(self::DISPATCH_ROLES, $message, $data);
    }

    public function toUserAndDispatchers(UserInterface $user, $message, array $data = [])
    {
        $this->toUserAndRoles($user, self::DISPATCH_ROLES, $message, $data);
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

        $this->realTimeMessageLogger->info(sprintf("Publishing event '%s' on channel %s [%s]",
            $payload['name'],
            $channel,
            $this->describeSubject($data)));

        $result = $this->centrifugoClient->publish(
            $channel,
            ['event' => $payload]
        );

        $this->logPublicationError($result, $payload['name'], [$channel]);
    }

    /**
     * @param string[] $roles
     * @param NamedMessage|string $message
     */
    public function toRoles($roles, $message, array $data = [])
    {
        $users = $this->userManager->findUsersByRoles($roles);

        $this->toUsers($users, $message, $data);
    }

    /**
     * @param UserInterface $user
     * @param string[] $roles
     * @param NamedMessage|string $message
     */
    public function toUserAndRoles($user, $roles, $message, array $data = [])
    {
        $users = $this->userManager->findUsersByRoles($roles);

        // If the user has any role selected, don't notify twice
        if (!in_array($user, $users, true)) {
            $users[] = $user;
        }

        $this->toUsers($users, $message, $data);
    }

    /**
     * @param UserInterface[] $users
     * @param NamedMessage|string $message
     */
    public function toUsers($users, $message, array $data = [])
    {
        if (count($users) === 0) {
            return;
        }

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

        $this->realTimeMessageLogger->info(sprintf("Broadcasting event '%s' [%s] on channels %s for users %s",
            $payload['name'],
            $this->describeSubject($data),
            implode(', ', $centrifugoChannels),
            implode(', ', array_map(function (UserInterface $user) {
                return $user->getUserIdentifier();
            }, $users))));

        // We use broadcast to reduce the number of HTTP requests
        $result = $this->centrifugoClient->broadcast(
            $centrifugoChannels,
            ['event' => $payload]
        );

        $this->logPublicationError($result, $payload['name'], $centrifugoChannels);

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
                return $user->getUserIdentifier();
            }, $users);

            $text = $message->forHumans($this->translator, $this->security->getUser());

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
        $username = $user instanceof UserInterface ? $user->getUserIdentifier() : $user;

        return sprintf('%s_events#%s', $this->namespace, $username);
    }

    private function getOrderChannelName(OrderInterface $order)
    {
        return sprintf('%s_order_events#%d', $this->namespace, $order->getId());
    }

    /**
     * @param UserInterface|string $user
     */
    public function publishEvent($user, array $payload)
    {
        $channel = $this->getEventsChannelName($user);

        // This method is used only for 'notifications' and 'notifications:count' events at the moment
        $this->realTimeMessageLogger->debug(sprintf("Publishing event '%s' on channel %s for user %s",
            $payload['name'],
            $channel,
            $user instanceof UserInterface ? $user->getUserIdentifier() : $user));

        $result = $this->centrifugoClient->publish($channel, ['event' => $payload]);

        $this->logPublicationError($result, $payload['name'], [$channel]);
    }

    /**
     * Centrifugo answers 200 with an `error` object in the body when it refuses a
     * publication, and phpcent only throws on a non-200. A refused publication is
     * therefore indistinguishable from a delivered one unless the response is
     * inspected, and the event just never reaches the dispatch board.
     *
     * @param mixed $result As returned by publish()/broadcast()
     * @param string[] $channels
     */
    private function logPublicationError($result, string $eventName, array $channels): void
    {
        $error = is_array($result) ? ($result['error'] ?? null) : null;

        if (null === $error) {
            return;
        }

        $this->realTimeMessageLogger->error(sprintf(
            "Centrifugo refused event '%s' on %d channel(s): %s",
            $eventName,
            count($channels),
            json_encode($error)
        ));
    }

    /**
     * A compact description of what the payload actually carries, appended to the
     * publication log. Without it the log only proves that *an* event was sent, not
     * that it held the state the dispatcher was waiting for -- a `task:done` still
     * carrying `status=TODO` looks identical to a correct one.
     */
    private function describeSubject(array $data): string
    {
        foreach (['task', 'order', 'tour', 'task_list'] as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }

            $subject = $data[$key];

            $parts = array_filter([
                $key . '#' . ($subject['id'] ?? $subject['@id'] ?? '?'),
                isset($subject['status']) ? 'status=' . $subject['status'] : null,
                isset($subject['state']) ? 'state=' . $subject['state'] : null,
                isset($subject['date']) ? 'date=' . $subject['date'] : null,
                isset($subject['updatedAt']) ? 'updatedAt=' . $subject['updatedAt'] : null,
            ]);

            return implode(' ', $parts);
        }

        return '-';
    }

    private function shouldNotifyEvent(string $messageName)
    {
        return $this->notificationPreferences->isEventEnabled($messageName);
    }
}
