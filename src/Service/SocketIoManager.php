<?php

namespace AppBundle\Service;

use AppBundle\Domain\Order\Event as OrderEvent;
use AppBundle\Domain\HumanReadableEventInterface;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Entity\ApiUser;
use AppBundle\Action\Utils\TokenStorageTrait;
use FOS\UserBundle\Model\UserManagerInterface;
use Redis;
use Ramsey\Uuid\Uuid;
use SimpleBus\Message\Name\NamedMessage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class SocketIoManager
{
    use TokenStorageTrait;

    private $redis;
    private $userManager;
    private $serializer;
    private $translator;

    public function __construct(
        Redis $redis,
        UserManagerInterface $userManager,
        TokenStorageInterface $tokenStorage,
        SerializerInterface $serializer,
        TranslatorInterface $translator)
    {
        $this->redis = $redis;
        $this->userManager = $userManager;
        $this->tokenStorage = $tokenStorage;
        $this->serializer = $serializer;
        $this->translator = $translator;
    }

    public function toAdmins($message, array $data = [])
    {
        $admins = $this->userManager->findUsersByRole('ROLE_ADMIN');
        foreach ($admins as $user) {
            $this->toUser($user, $message, $data);
        }
    }

    public function toUserAndAdmins(UserInterface $user, $message, array $data = [])
    {
        $users = $this->userManager->findUsersByRole('ROLE_ADMIN');

        // If the user is also an admin, don't notify twice
        if (!in_array($user, $users, true)) {
            $users[] = $user;
        }

        foreach ($users as $user) {
            $this->toUser($user, $message, $data);
        }
    }

    public function toUser(UserInterface $user, $message, array $data = [])
    {
        $messageName = $message instanceof NamedMessage ? $message::messageName() : $message;

        if ($message instanceof SerializableEventInterface && empty($data)) {
            $data = $message->normalize($this->serializer);
        }

        $channel = sprintf('users:%s', $user->getUsername());
        $payload = json_encode([
            'name' => $messageName,
            'data' => $data
        ]);

        $this->redis->publish($channel, $payload);
        $this->createNotification($user, $message);
    }

    private function createNotification(UserInterface $user, $message)
    {
        if ($message instanceof HumanReadableEventInterface) {

            $uuid = Uuid::uuid4()->toString();
            $listKey = sprintf('user:%s:notifications', $user->getUsername());
            $hashKey = sprintf('user:%s:notifications_data', $user->getUsername());

            $payload = [
                'id' => $uuid,
                'message' => $message->forHumans($this->translator, $this->getUser()),
                'timestamp' => (new \DateTime())->getTimestamp()
            ];

            $this->redis->lpush($listKey, $uuid);
            $this->redis->hset($hashKey, $uuid, json_encode($payload));

            $notificationsPayload = json_encode([
                'name' => 'notifications',
                'data' => $payload,
            ]);
            $notificationsCountPayload = json_encode([
                'name' => 'notifications:count',
                'data' => $this->redis->llen($listKey),
            ]);

            $channel = sprintf('users:%s', $user->getUsername());

            $this->redis->publish($channel, $notificationsPayload);
            $this->redis->publish($channel, $notificationsCountPayload);
        }
    }

    public function getLastNotifications(UserInterface $user)
    {
        $listKey = sprintf('user:%s:notifications', $user->getUsername());
        $hashKey = sprintf('user:%s:notifications_data', $user->getUsername());

        $uuids = $this->redis->lrange($listKey, 0, 5);

        $notifications = [];
        foreach ($uuids as $uuid) {
            $data = $this->redis->hget($hashKey, $uuid);
            if ($data) {
                $notifications[] = json_decode($data, true);
            }
        }

        return $notifications;
    }

    public function countNotifications(UserInterface $user)
    {
        $listKey = sprintf('user:%s:notifications', $user->getUsername());

        return $this->redis->llen($listKey);
    }

    public function markAsRead(UserInterface $user, array $uuids = [])
    {
        $listKey = sprintf('user:%s:notifications', $user->getUsername());
        $hashKey = sprintf('user:%s:notifications_data', $user->getUsername());

        foreach ($uuids as $uuid) {
            $this->redis->lrem($listKey, $uuid, 0);
            $this->redis->hdel($hashKey, $uuid);
        }

        $notificationsCountPayload = json_encode([
            'name' => 'notifications:count',
            'data' => $this->redis->llen($listKey),
        ]);

        $channel = sprintf('users:%s', $user->getUsername());

        $this->redis->publish($channel, $notificationsCountPayload);
    }
}
