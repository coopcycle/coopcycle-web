<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Utils\OrderTimeHelper;
use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\Common\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $tokenStorage;
    private $orderTimeHelper;
    private $logger;

    public function __construct(
        ManagerRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        OrderTimeHelper $orderTimeHelper,
        LoggerInterface $logger
    ) {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => [
                ['preValidate', EventPriorities::PRE_VALIDATE],
            ],
        ];
    }

    private function getUser()
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

    public function preValidate(ViewEvent $event)
    {
        $result = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!($result instanceof Order && Request::METHOD_POST === $method)) {
            return;
        }

        $order = $result;

        // // Convert date to DateTime
        // if (!$delivery->getDate() instanceof \DateTime) {
        //     $delivery->setDate(new \DateTime($delivery->getDate()));
        // }

        // Make sure customer is set
        if (null === $order->getCustomer()) {
            $order->setCustomer($this->getUser());
        }

        if ($order->isFoodtech() && null === $order->getId() && null === $order->getShippedAt()) {
            $asap = $this->orderTimeHelper->getAsap($order);
            $order->setShippedAt(new \DateTime($asap));
        }

        $event->setControllerResult($order);
    }
}
