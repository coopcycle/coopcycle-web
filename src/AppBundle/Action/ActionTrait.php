<?php

namespace AppBundle\Action;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Predis\Client as Redis;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;

trait ActionTrait
{
    protected $tokenStorage;
    protected $redis;
    protected $doctrine;
    protected $orderManager;
    protected $deliveryManager;

    public function __construct(TokenStorageInterface $tokenStorage, Redis $redis,
        DoctrineRegistry $doctrine, OrderManager $orderManager, DeliveryManager $deliveryManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
        $this->doctrine = $doctrine;
        $this->orderManager = $orderManager;
        $this->deliveryManager = $deliveryManager;
    }

    protected function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    protected function verifyRole($role, $message)
    {
        $user = $this->getUser();

        if (!$user->hasRole($role)) {
            throw new AccessDeniedHttpException(sprintf($message, $user->getId()));
        }
    }
}
