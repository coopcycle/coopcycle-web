<?php

namespace AppBundle\EventSubscriber;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Core\EventListener\EventPriorities;
use AppBundle\Entity\Order;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use AppBundle\Utils\MetricsHelper;
use M6Web\Component\Statsd\Client as StatsdClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    private $tokenStorage;
    private $deliveryServiceFactory;
    private $validator;
    private $metricsHelper;
    private $logger;

    public function __construct(TokenStorageInterface $tokenStorage,
        DeliveryServiceFactory $deliveryServiceFactory, ValidatorInterface $validator,
        MetricsHelper $metricsHelper,
        LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->deliveryServiceFactory = $deliveryServiceFactory;
        $this->validator = $validator;
        $this->metricsHelper = $metricsHelper;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => [
                ['preValidate', EventPriorities::PRE_VALIDATE],
                ['postValidate', EventPriorities::POST_VALIDATE],
            ],
            'order.created' => 'onOrderCreated',
            'order.accepted' => 'onOrderAccepted',
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

    private function getDeliveryService(Order $order)
    {
        return $this->deliveryServiceFactory->createForRestaurant($order->getRestaurant());
    }

    public function preValidate(GetResponseForControllerResultEvent $event)
    {
        $order = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$order instanceof Order || Request::METHOD_POST !== $method) {
            return;
        }

        $delivery = $order->getDelivery();

        // Convert date to DateTime
        if (!$delivery->getDate() instanceof \DateTime) {
            $delivery->setDate(new \DateTime($delivery->getDate()));
        }

        // Make sure customer is set
        if (null === $order->getCustomer()) {
            $order->setCustomer($this->getUser());
        }

        // Make sure models are associated
        $delivery->setOrder($order);

        // Make sure originAddress is set
        if (null === $delivery->getOriginAddress()) {
            $delivery->setOriginAddress($order->getRestaurant()->getAddress());
        }

        if (!$delivery->isCalculated()) {
            $this->getDeliveryService($order)->calculate($delivery);
        }

        $event->setControllerResult($order);
    }

    public function postValidate(GetResponseForControllerResultEvent $event)
    {
        $order = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$order instanceof Order || Request::METHOD_POST !== $method) {
            return;
        }

        $errors = $this->validator->validate($order, null, ['order']);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    public function onOrderCreated(Event $event)
    {
        $this->logger->info('Order created');
        $this->metricsHelper->incrementOrdersWaiting();
    }

    public function onOrderAccepted(Event $event)
    {
        $this->logger->info('Order accepted');
        $this->metricsHelper->decrementOrdersWaiting();
    }
}
