<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\TopBarNotification;
use AppBundle\Service\LiveUpdates;
use Redis;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Ramsey\Uuid\Uuid;

#[AsMessageHandler]
class TopBarNotificationHandler
{
    private $redis;
    private $liveUpdates;

    public const MAX_NOTIFICATIONS = 100;

    public function __construct(
        Redis $redis,
        LiveUpdates $liveUpdates)
    {
        $this->redis = $redis;
        $this->liveUpdates = $liveUpdates;
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

            $this->redis->lPush($listKey, $uuid);
            $this->redis->hSet($hashKey, $uuid, json_encode($payload));

            $length = $this->redis->lLen($listKey);
            if ($length > self::MAX_NOTIFICATIONS) {
                $itemsToRemove = $this->redis->lRange($listKey, self::MAX_NOTIFICATIONS, $length - 1);
                $this->redis->lTrim($listKey, 0, self::MAX_NOTIFICATIONS - 1);
                if (count($itemsToRemove) > 0) {
                    $this->redis->hDel($hashKey, ...$itemsToRemove);
                }
            }

            $notificationsPayload = [
                'name' => 'notifications',
                'data' => $payload,
            ];
            $notificationsCountPayload = [
                'name' => 'notifications:count',
                'data' => $this->redis->llen($listKey),
            ];

            $this->liveUpdates->publishEvent($username, $notificationsPayload);
            $this->liveUpdates->publishEvent($username, $notificationsCountPayload);
        }
    }
}
