<?php

namespace AppBundle\Service;

use AppBundle\Message\UpdateNotificationsCount;
use Redis;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TopBarNotifications
{
    const NOTIFICATIONS_OFFSET = 20;

    private $redis;
    private $messageBus;

    public function __construct(
        Redis $redis,
        MessageBusInterface $messageBus)
    {
        $this->redis = $redis;
        $this->messageBus = $messageBus;
    }

    public function getNotifications(UserInterface $user, $page = 1)
    {
        $listKey = sprintf('user:%s:notifications', $user->getUsername());
        $hashKey = sprintf('user:%s:notifications_data', $user->getUsername());

        $uuids = $this->redis->lrange($listKey, self::NOTIFICATIONS_OFFSET * ($page - 1) , (self::NOTIFICATIONS_OFFSET * $page) - 1);

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

        $count = $this->redis->llen($listKey);

        $this->messageBus->dispatch(
            new UpdateNotificationsCount($user->getUsername(), $count)
        );
    }

    public function markAllAsRead(UserInterface $user)
    {
        $listKey = sprintf('user:%s:notifications', $user->getUsername());
        $hashKey = sprintf('user:%s:notifications_data', $user->getUsername());

        $this->redis->del($listKey);
        $this->redis->del($hashKey);

        $count = $this->redis->llen($listKey);

        $this->messageBus->dispatch(
            new UpdateNotificationsCount($user->getUsername(), $count)
        );
    }
}
