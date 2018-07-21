<?php

namespace AppBundle\Service;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use Symfony\Bridge\Twig\TwigEngine;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Translation\TranslatorInterface;

class EmailManager
{
    private $mailer;
    private $templating;
    private $translator;
    private $settingsManager;
    private $transactionalAddress;

    public function __construct(
        \Swift_Mailer $mailer,
        TwigEngine $templating,
        TranslatorInterface $translator,
        SettingsManager $settingsManager,
        $transactionalAddress)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->translator = $translator;
        $this->settingsManager = $settingsManager;
        $this->transactionalAddress = $transactionalAddress;
    }

    private function getFrom()
    {
        return [
            $this->transactionalAddress => $this->settingsManager->get('brand_name')
        ];
    }

    private function getReplyTo()
    {
        return [
            $this->settingsManager->get('administrator_email') => $this->settingsManager->get('brand_name')
        ];
    }

    public function createHtmlMessage($subject = null, $body = null)
    {
        $message = \Swift_Message::newInstance($subject);

        if ($body) {
            $message->setBody($body, 'text/html');
        }

        $message->setSender($this->getFrom());
        $message->setFrom($this->getFrom());

        return $message;
    }

    public function createHtmlMessageWithReplyTo($subject = null, $body = null)
    {
        $message = $this->createHtmlMessage($subject, $body);

        // Allow replying to the administrator
        $message->setReplyTo($this->getReplyTo());

        return $message;
    }

    public function send(\Swift_Message $message)
    {
        foreach ($message->getTo() as $address => $name) {
            if (preg_match('/@demo.coopcycle.org$/', $address)) {
                return;
            }
        }

        $this->mailer->send($message);
    }

    public function sendTo(\Swift_Message $message, $to)
    {
        $message->setTo($to);

        $this->send($message);
    }

    public function createOrderCreatedMessageForCustomer(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.created.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->templating->render('@App/Emails/Order/created.html.twig', [
            'order' => $order,
        ]);

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderCreatedMessageForOwner(OrderInterface $order)
    {
        $subject = $this->translator->trans(
            'owner.order.created.subject',
            ['%order.number%' => $order->getNumber()],
            'emails');
        $body = $this->templating->render('@App/Emails/Order/created.html.twig', [
            'order' => $order,
            'is_owner' => true
        ]);

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCreatedMessageForAdmin(OrderInterface $order)
    {
        $subject = $this->translator->trans('admin.order.created.subject', [], 'emails');
        $body = $this->templating->render('@App/Emails/Order/created.html.twig', [
            'order' => $order,
            'is_admin' => true
        ]);

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCancelledMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.cancelled.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->templating->render('@App/Emails/Order/cancelled.html.twig', [
            'order' => $order,
        ]);

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderAcceptedMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.accepted.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->templating->render('@App/Emails/Order/accepted.html.twig', [
            'order' => $order,
        ]);

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }
}
