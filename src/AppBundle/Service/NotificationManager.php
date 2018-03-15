<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\StripePayment;
use Symfony\Bridge\Twig\TwigEngine;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Translation\TranslatorInterface;

class NotificationManager
{
    private $mailer;
    private $templating;
    private $translator;
    private $settingsManager;
    private $transactionalAddress;

    public function __construct(\Swift_Mailer $mailer, TwigEngine $templating,
        TranslatorInterface $translator, SettingsManager $settingsManager, $transactionalAddress)
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

    public function notifyOrderCreated(Order $order)
    {
        if (preg_match('/@demo.coopcycle.org$/', $order->getCustomer()->getEmail())) {
            return;
        }

        $emailBody = $this->templating->render('AppBundle::Emails/orderConfirmation.html.twig', [
            'order' => $order,
            'orderId' => $order->getId()
        ]);

        $email = new \Swift_Message($this->translator->trans('order.confirmationMail.subject', ['%orderId%' => $order->getId()], 'emails'));
        $email->setFrom($this->getFrom());
        $email->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
        $email->setBody($emailBody, 'text/html');

        $this->mailer->send($email);
    }

    public function notifyOrderAccepted(Order $order)
    {
        if (preg_match('/@demo.coopcycle.org$/', $order->getCustomer()->getEmail())) {
            return;
        }

        $emailBody = $this->templating->render('AppBundle::Emails/orderAccepted.html.twig', [
            'order' => $order,
            'orderId' => $order->getId()
        ]);

        $email = new \Swift_Message($this->translator->trans('order.acceptedMail.subject', ['%orderId%' => $order->getId()], 'emails'));
        $email->setFrom($this->getFrom());
        $email->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
        $email->setBody($emailBody, 'text/html');

        $this->mailer->send($email);
    }

    public function notifyOrderCanceled(Order $order)
    {
        if (preg_match('/@demo.coopcycle.org$/', $order->getCustomer()->getEmail())) {
            return;
        }

        $emailBody = $this->templating->render('AppBundle::Emails/orderCancelled.html.twig', [
            'order' => $order,
            'orderId' => $order->getId()
        ]);

        $email = new \Swift_Message($this->translator->trans('order.cancellationMail.subject', ['%orderId%' => $order->getId()], 'emails'));
        $email->setFrom($this->getFrom());
        $email->setTo([$order->getCustomer()->getEmail() => $order->getCustomer()->getFullName()]);
        $email->setBody($emailBody, 'text/html');

        $this->mailer->send($email);
    }

    public function notifyDeliveryToBeConfirmed(Delivery $delivery, $to)
    {
        $email = new \Swift_Message($this->translator->trans('delivery.to_be_confirmed.subject', [], 'emails'));
        $email->setFrom($this->getFrom());
        $email->setTo($to);
        $email->setBody($this->templating->render('@App/Emails/Delivery/toBeConfirmed.html.twig', [
            'delivery' => $delivery,
        ]), 'text/html');

        $this->mailer->send($email);
    }

    public function notifyDeliveryHasToBeConfirmed(Delivery $delivery, $to)
    {
        $email = new \Swift_Message($this->translator->trans('delivery.has_to_be_confirmed.subject', [], 'emails'));
        $email->setFrom($this->getFrom());
        $email->setTo($to);
        $email->setBody($this->templating->render('@App/Emails/Delivery/hasToBeConfirmed.html.twig', [
            'delivery' => $delivery,
        ]), 'text/html');

        $this->mailer->send($email);
    }

    public function notifyDeliveryConfirmed(OrderInterface $order, $to)
    {
        $email = new \Swift_Message($this->translator->trans('delivery.confirmed.subject', [], 'emails'));
        $email->setFrom($this->getFrom());
        $email->setTo($to);
        $email->setBody($this->templating->render('@App/Emails/Delivery/confirmed.html.twig', [
            'order' => $order,
        ]), 'text/html');

        $this->mailer->send($email);
    }
}
