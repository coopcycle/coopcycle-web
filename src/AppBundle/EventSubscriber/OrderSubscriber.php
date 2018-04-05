<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Event\OrderAcceptEvent;
use AppBundle\Event\OrderCancelEvent;
use AppBundle\Event\OrderCreateEvent;
use AppBundle\Event\TaskDoneEvent;
use AppBundle\Service\NotificationManager;
use AppBundle\Service\OrderManager;
use AppBundle\Utils\MetricsHelper;
use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\Common\Persistence\ManagerRegistry;
use M6Web\Component\Statsd\Client as StatsdClient;
use Predis\Client as Redis;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $tokenStorage;
    private $notificationManager;
    private $metricsHelper;
    private $redis;
    private $orderManager;
    private $logger;

    public function __construct(
        ManagerRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        NotificationManager $notificationManager,
        MetricsHelper $metricsHelper,
        Redis $redis,
        OrderManager $orderManager,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->notificationManager = $notificationManager;
        $this->metricsHelper = $metricsHelper;
        $this->redis = $redis;
        $this->orderManager = $orderManager;
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

    public function onOrderCreated(Event $event)
    {
        $order = $event->getOrder();

        $this->logger->info(sprintf('Order #%d created', $order->getId()));

        $this->notificationManager->notifyOrderCreated($order);
        $this->metricsHelper->incrementOrdersWaiting();
    }

    public function onOrderAccepted(OrderAcceptEvent $event)
    {
        $order = $event->getOrder();

        $this->logger->info(sprintf('Order #%d accepted', $order->getId()));

        $this->notificationManager->notifyOrderAccepted($order);
        $this->metricsHelper->decrementOrdersWaiting();

        $originAddress = $order->getDelivery()->getOriginAddress();
        $deliveryAddress = $order->getDelivery()->getDeliveryAddress();

        $this->redis->geoadd(
            'deliveries:geo',
            $originAddress->getGeo()->getLongitude(),
            $originAddress->getGeo()->getLatitude(),
            'delivery:'.$order->getDelivery()->getId()
        );

        $this->redis->geoadd(
            'restaurants:geo',
            $originAddress->getGeo()->getLongitude(),
            $originAddress->getGeo()->getLatitude(),
            'delivery:'.$order->getDelivery()->getId()
        );
        $this->redis->geoadd(
            'delivery_addresses:geo',
            $deliveryAddress->getGeo()->getLongitude(),
            $deliveryAddress->getGeo()->getLatitude(),
            'delivery:'.$order->getDelivery()->getId()
        );

        $this->redis->lpush(
            'deliveries:waiting',
            $order->getDelivery()->getId()
        );
    }

    public function onOrderCanceled(Event $event)
    {
        $order = $event->getOrder();

        $this->logger->info(sprintf('Order #%d canceled', $order->getId()));

        $this->notificationManager->notifyOrderCanceled($order);
        $this->metricsHelper->decrementOrdersWaiting();

        $this->redis->lrem('deliveries:waiting', 0, $order->getDelivery()->getId());
    }

    public function onTaskDone(TaskDoneEvent $event)
    {
        $task = $event->getTask();
        $user = $event->getUser();

        $delivery = $task->getDelivery();

        if ($task->isDropoff() && null !== $delivery) {

            $order = $delivery->getSyliusOrder();

            if (null !== $order && $order->isFoodtech()) {

                $this->orderManager->capturePayment($order);
                $this->orderManager->fulfill($order);

                $this->doctrine->getManager()->flush();
            }
        }
    }
}
