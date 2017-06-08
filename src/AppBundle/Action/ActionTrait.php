<?php

namespace AppBundle\Action;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\SerializerInterface;
use AppBundle\Entity\OrderRepository;
use AppBundle\Entity\ApiUser;
use AppBundle\Service\PaymentService;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;

trait ActionTrait
{
    protected $tokenStorage;
    protected $redis;
    protected $orderRepository;
    protected $serializer;
    protected $doctrine;
    protected $eventDispatcher;
    protected $paymentService;

    public function __construct(TokenStorageInterface $tokenStorage, Redis $redis,
        OrderRepository $orderRepository, SerializerInterface $serializer, DoctrineRegistry $doctrine,
        EventDispatcher $eventDispatcher, PaymentService $paymentService)
    {
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->doctrine = $doctrine;
        $this->eventDispatcher = $eventDispatcher;
        $this->paymentService = $paymentService;
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
}
