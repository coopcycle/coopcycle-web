<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\TopBarNotification;
use AppBundle\Service\SocketIoManager;
use Redis;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Ramsey\Uuid\Uuid;

class TopBarNotificationHandler implements MessageHandlerInterface
{
    private $redis;
    private $socketIoManager;

    public function __construct(
        Redis $redis,
        SocketIoManager $socketIoManager)
    {
        $this->redis = $redis;
        $this->socketIoManager = $socketIoManager;
    }

    public function __invoke(TopBarNotification $message)
    {
        foreach ($message->getUsernames() as $username) {

            $uuid = Uuid::uuid4()->toString();

            $listKey = sprintf('user:%s:notifications', $username);
            $hashKey = sprintf('user:%s:notifications_data', $username);

            $payload = [
                'id' => $uuid,
                'message' => $message->getMessage(),
                'timestamp' => (new \DateTime())->getTimestamp()
            ];

            $this->redis->lpush($listKey, $uuid);
            $this->redis->hset($hashKey, $uuid, json_encode($payload));

            $notificationsPayload = [
                'name' => 'notifications',
                'data' => $payload,
            ];
            $notificationsCountPayload = [
                'name' => 'notifications:count',
                'data' => $this->redis->llen($listKey),
            ];

            $this->socketIoManager->publishEvent($username, $notificationsPayload);
            $this->socketIoManager->publishEvent($username, $notificationsCountPayload);
        }
    }
}
