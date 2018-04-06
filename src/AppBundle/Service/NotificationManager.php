<?php

namespace AppBundle\Service;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Notification;
use Doctrine\Common\Persistence\ManagerRegistry;
use Predis\Client as Redis;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class NotificationManager
{
    private $doctrine;
    private $redis;
    private $serializer;
    private $translator;
    private $notificationRepository;

    public function __construct(
        ManagerRegistry $doctrine,
        Redis $redis,
        SerializerInterface $serializer,
        TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->serializer = $serializer;
        $this->translator = $translator;
        $this->notificationRepository = $this->doctrine->getRepository(Notification::class);
    }

    public function push(Notification $notification)
    {
        $this->doctrine->getManagerForClass(Notification::class)->persist($notification);
        $this->doctrine->getManagerForClass(Notification::class)->flush();

        $channel = sprintf('user:%s:notifications', $notification->getUser()->getUsername());
        $this->redis->publish($channel, $this->serializer->serialize($notification, 'json'));

        $this->publishCount($notification->getUser());
    }

    public function publishCount(ApiUser $user)
    {
        $count = $this->notificationRepository->countUnreadByUser($user);
        $channel = sprintf('user:%s:notifications:count', $user->getUsername());
        $this->redis->publish($channel, $count);
    }
}
