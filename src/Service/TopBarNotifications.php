<?php

namespace AppBundle\Service;

use AppBundle\Message\UpdateNotificationsCount;
use Redis;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TopBarNotifications
{
    private $redis;
    private $messageBus;

    public function __construct(
        Redis $redis,
        MessageBusInterface $messageBus)
    {
        $this->redis = $redis;
        $this->messageBus = $messageBus;
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

        $count = $this->redis->llen($listKey);

        $this->messageBus->dispatch(
            new UpdateNotificationsCount($user->getUsername(), $count)
        );
    }
}
