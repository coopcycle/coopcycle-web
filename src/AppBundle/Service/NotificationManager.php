<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\StripePayment;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\Translation\TranslatorInterface;

class NotificationManager
{
    private $mailer;
    private $templating;
    private $translator;
    private $options;

    public function __construct(\Swift_Mailer $mailer, TwigEngine $templating, TranslatorInterface $translator, array $options)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->translator = $translator;
        $this->options = $options;
    }

    public function notifyOrderCreated(Order $order)
    {
        if (preg_match('/@demo.coopcycle.org$/', $order->getCustomer()->getEmail())) {
            return;
        }

        $emailAddress = $this->options['transactional_address'];
        $emailName = $this->options['transactional_sender_name'];

        $emailBody = $this->templating->render('AppBundle::Emails/orderConfirmation.html.twig', [
            'order' => $order,
            'orderId' => $order->getId()
        ]);

        $email = new \Swift_Message($this->translator->trans('order.confirmationMail.subject', ['%orderId%' => $order->getId()], 'emails'));
        $email->setFrom([$emailAddress => $emailName]);
        $email->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
        $email->setBody($emailBody, 'text/html');

        $this->mailer->send($email);
    }

    public function notifyOrderAccepted(Order $order)
    {
        if (preg_match('/@demo.coopcycle.org$/', $order->getCustomer()->getEmail())) {
            return;
        }

        $emailAddress = $this->options['transactional_address'];
        $emailName = $this->options['transactional_sender_name'];

        $emailBody = $this->templating->render('AppBundle::Emails/orderAccepted.html.twig', [
            'order' => $order,
            'orderId' => $order->getId()
        ]);

        $email = new \Swift_Message($this->translator->trans('order.acceptedMail.subject', ['%orderId%' => $order->getId()], 'emails'));
        $email->setFrom([$emailAddress => $emailName]);
        $email->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
        $email->setBody($emailBody, 'text/html');

        $this->mailer->send($email);
    }

    public function notifyOrderCanceled(Order $order)
    {
        if (preg_match('/@demo.coopcycle.org$/', $order->getCustomer()->getEmail())) {
            return;
        }

        $emailAddress = $this->options['transactional_address'];
        $emailName = $this->options['transactional_sender_name'];

        $emailBody = $this->templating->render('AppBundle::Emails/orderCancelled.html.twig', [
            'order' => $order,
            'orderId' => $order->getId()
        ]);

        $email = new \Swift_Message($this->translator->trans('order.cancellationMail.subject', ['%orderId%' => $order->getId()], 'emails'));
        $email->setFrom([$emailAddress => $emailName]);
        $email->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
        $email->setBody($emailBody, 'text/html');

        $this->mailer->send($email);
    }

    public function notifyDeliveryToBeConfirmed(Delivery $delivery, $to)
    {
        $emailAddress = $this->options['transactional_address'];
        $emailName = $this->options['transactional_sender_name'];

        $email = new \Swift_Message($this->translator->trans('delivery.to_be_confirmed.subject', [], 'emails'));
        $email->setFrom([$emailAddress => $emailName]);
        $email->setTo($to);
        $email->setBody($this->templating->render('@App/Emails/Delivery/toBeConfirmed.html.twig', [
            'delivery' => $delivery,
        ]), 'text/html');

        $this->mailer->send($email);
    }

    public function notifyDeliveryConfirmed(Delivery $delivery, StripePayment $stripePayment)
    {
        $emailAddress = $this->options['transactional_address'];
        $emailName = $this->options['transactional_sender_name'];

        $email = new \Swift_Message($this->translator->trans('delivery.confirmed.subject', [], 'emails'));
        $email->setFrom([$emailAddress => $emailName]);
        $email->setTo($stripePayment->getUser()->getEmail());
        $email->setBody($this->templating->render('@App/Emails/Delivery/confirmed.html.twig', [
            'delivery' => $delivery,
            'stripe_payment' => $stripePayment
        ]), 'text/html');

        $this->mailer->send($email);
    }
}
