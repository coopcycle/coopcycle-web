<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Event\OrderAcceptEvent;
use AppBundle\Event\OrderCancelEvent;
use AppBundle\Event\OrderCreateEvent;
use AppBundle\Event\TaskDoneEvent;
use AppBundle\Service\OrderManager;
use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\Common\Persistence\ManagerRegistry;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $redis;
    private $tokenStorage;
    private $orderManager;
    private $serializer;
    private $logger;

    public function __construct(
        ManagerRegistry $doctrine,
        Redis $redis,
        TokenStorageInterface $tokenStorage,
        OrderManager $orderManager,
        SerializerInterface $serializer,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->tokenStorage = $tokenStorage;
        $this->orderManager = $orderManager;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => [
                ['preValidate', EventPriorities::PRE_VALIDATE],
            ],
            OrderCreateEvent::NAME => 'onOrderCreated',
            OrderAcceptEvent::NAME => 'onOrderAccepted',
            OrderCancelEvent::NAME => 'onOrderCanceled',
            TaskDoneEvent::NAME    => 'onTaskDone',
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

    public function preValidate(GetResponseForControllerResultEvent $event)
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

        $event->setControllerResult($order);
    }

    public function onOrderCreated(OrderCreateEvent $event)
    {
        $order = $event->getOrder();

        $this->logger->info(sprintf('Order #%d created', $order->getId()));
    }

    public function onOrderAccepted(OrderAcceptEvent $event)
    {
        $order = $event->getOrder();

        $this->logger->info(sprintf('Order #%d accepted', $order->getId()));

        $this->redis->publish(
            sprintf('order:%d:state_changed', $order->getId()),
            $this->serializer->serialize($order, 'json', ['groups' => ['order']])
        );
    }

    public function onOrderCanceled(OrderCancelEvent $event)
    {
        $order = $event->getOrder();

        $this->logger->info(sprintf('Order #%d canceled', $order->getId()));
    }

    public function onTaskDone(TaskDoneEvent $event)
    {
        $task = $event->getTask();
        $user = $event->getUser();

        $delivery = $task->getDelivery();

        if ($task->isDropoff() && null !== $delivery) {

            $order = $delivery->getOrder();

            if (null !== $order && $order->isFoodtech()) {

                $this->orderManager->fulfill($order);
                $this->doctrine->getManager()->flush();
            }
        }
    }
}
