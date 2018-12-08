<?php

namespace AppBundle\Service;

use AppBundle\Entity\ApiUser;
use FOS\UserBundle\Model\UserManager;
use Predis\Client as Redis;
use Symfony\Component\Security\Core\User\UserInterface;

class SocketIoManager
{
    private $redis;
    private $userManager;

    public function __construct(
        Redis $redis,
        UserManager $userManager)
    {
        $this->redis = $redis;
        $this->userManager = $userManager;
    }

    public function toAdmins($name, array $data = [])
    {
        $admins = $this->userManager->findUsersByRole('ROLE_ADMIN');
        foreach ($admins as $user) {
            $this->toUser($user, $name, $data);
        }
    }

    public function toUser(UserInterface $user, $name, array $data = [])
    {
        $channel = sprintf('users:%s', $user->getUsername());
        $message = json_encode([
            'name' => $name,
            'data' => $data
        ]);

        $this->redis->publish($channel, $message);
    }
}
