<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\Utils\TokenStorageTrait;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Predis\Client as Redis;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use AppBundle\Service\OrderManager;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;

abstract class Base
{
    use TokenStorageTrait;

    protected $redis;
    protected $doctrine;
    protected $orderManager;

    public function __construct(TokenStorageInterface $tokenStorage, Redis $redis,
        DoctrineRegistry $doctrine, OrderManager $orderManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
        $this->doctrine = $doctrine;
        $this->orderManager = $orderManager;
    }

    protected function verifyRole($role, $message)
    {
        $user = $this->getUser();

        if (!$user->hasRole($role)) {
            throw new AccessDeniedHttpException(sprintf($message, $user->getId()));
        }
    }
}
