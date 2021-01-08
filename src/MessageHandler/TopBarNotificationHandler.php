<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\TopBarNotification;
use phpcent\Client as CentrifugoClient;
use Ramsey\Uuid\Uuid;
use Redis;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class TopBarNotificationHandler implements MessageHandlerInterface
{
    private $redis;
    private $centrifugoClient;
    private $namespace;

    public function __construct(
        Redis $redis,
        CentrifugoClient $centrifugoClient,
        string $namespace)
    {
        $this->redis = $redis;
        $this->centrifugoClient = $centrifugoClient;
        $this->namespace = $namespace;
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

            $this->centrifugoClient->publish(
                $this->getEventsChannelName($username),
                ['event' => $notificationsPayload]
            );
            $this->centrifugoClient->publish(
                $this->getEventsChannelName($username),
                ['event' => $notificationsCountPayload]
            );
        }
    }

    private function getEventsChannelName(string $username)
    {
        return sprintf('%s_events#%s', $this->namespace, $username);
    }
}
