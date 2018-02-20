<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Order;
use AppBundle\Entity\OrderEvent;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class OrderListener
{
    private $tokenStorage;
    private $redis;
    private $serializer;
    private $orderManager;
    private $eventDispatcher;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        Redis $redis,
        SerializerInterface $serializer,
        OrderManager $orderManager,
        DeliveryManager $deliveryManager,
        TranslatorInterface $translator,
        $templating,
        \Swift_Mailer $swiftMailer,
        $transactionalEmailAddress,
        $transactionalEmailName,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->tokenStorage = $tokenStorage;
        $this->redis = $redis;
        $this->serializer = $serializer;
        $this->orderManager = $orderManager;
        $this->deliveryManager = $deliveryManager;
        $this->translator = $translator;
        $this->templating = $templating;
        $this->mailer = $swiftMailer;
        $this->transactionalEmailAddress = $transactionalEmailAddress;
        $this->transactionalEmailName = $transactionalEmailName;
        $this->eventDispatcher = $eventDispatcher;
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

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(Order $order, LifecycleEventArgs $args)
    {
        $delivery = $order->getDelivery();

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

        // Apply taxes
        $this->orderManager->applyTaxes($order);

        if (!$delivery->isCalculated()) {
            $this->deliveryManager->calculate($delivery);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(Order $order, LifecycleEventArgs $args)
    {
        $deliveryId = $order->getDelivery() ? $order->getDelivery()->getId() : null;

        $this->redis->publish('order_events', json_encode([
            'delivery' => $deliveryId,
            'order' => $order->getId(),
            'status' => $order->getStatus(),
            'timestamp' => (new \DateTime())->getTimestamp(),
        ]));

        $this->sendTransactionalEmails($order);

        $this->eventDispatcher->dispatch('order.created');
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(Order $order, LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();

        $orderEvent = new OrderEvent($order, $order->getStatus());
        $em->persist($orderEvent);
        $em->flush();

        $deliveryId = $order->getDelivery() ? $order->getDelivery()->getId() : null;

        $this->redis->publish('order_events', json_encode([
            'delivery' => $deliveryId,
            'order' => $order->getId(),
            'status' => $order->getStatus(),
            'timestamp' => (new \DateTime())->getTimestamp(),
        ]));

        $this->sendTransactionalEmails($order);
    }

    protected function sendTransactionalEmails(Order $order)
    {
        if (preg_match('/@demo.coopcycle.org$/', $order->getCustomer()->getEmail())) {
            return;
        }

        // order placed
        if ($order->getStatus() === Order::STATUS_WAITING) {
            $mail = new \Swift_Message($this->translator->trans('order.confirmationMail.subject', ['%orderId%' => $order->getId()]));
            $mail->setFrom([$this->transactionalEmailAddress => $this->transactionalEmailName]);
            $mail->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
            $mail->setBody(
                $this->templating->render(
                    'AppBundle::Emails/orderConfirmation.html.twig',
                    ['order'=> $order, 'orderId' => $order->getId()]),
                'text/html'
            );

            $this->mailer->send($mail);

            // order accepted
        } else if ($order->getStatus() === Order::STATUS_ACCEPTED) {
            $mail = new \Swift_Message($this->translator->trans('order.acceptedMail.subject', ['%orderId%' => $order->getId()]));
            $mail->setFrom([$this->transactionalEmailAddress => $this->transactionalEmailName]);
            $mail->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
            $mail->setBody(
                $this->templating->render(
                    'AppBundle::Emails/orderAccepted.html.twig',
                    ['order'=> $order, 'orderId' => $order->getId()]),
                'text/html'
            );

            $this->mailer->send($mail);

            // order canceled
        } else if ($order->getStatus() === Order::STATUS_CANCELED) {
            $mail = new \Swift_Message($this->translator->trans('order.cancellationMail.subject', ['%orderId%' => $order->getId()]));
            $mail->setFrom([$this->transactionalEmailAddress => $this->transactionalEmailName]);
            $mail->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
            $mail->setBody(
                $this->templating->render(
                    'AppBundle::Emails/orderCancelled.html.twig',
                    ['order'=> $order, 'orderId' => $order->getId()]),
                'text/html'
            );

            $this->mailer->send($mail);
        }
    }
}
