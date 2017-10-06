<?php

namespace AppBundle\Action;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Predis\Client as Redis;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Service\OrderManager;
use AppBundle\Service\RoutingInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;

trait ActionTrait
{
    protected $tokenStorage;
    protected $redis;
    protected $deliveryRepository;
    protected $doctrine;
    protected $orderManager;
    protected $routing;

    public function __construct(TokenStorageInterface $tokenStorage, Redis $redis,
        DeliveryRepository $deliveryRepository, DoctrineRegistry $doctrine,
        OrderManager $orderManager, RoutingInterface $routing)
    {
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
        $this->deliveryRepository = $deliveryRepository;
        $this->doctrine = $doctrine;
        $this->orderManager = $orderManager;
        $this->routing = $routing;
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
