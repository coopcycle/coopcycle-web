<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Entity\Invitation;
use Twig\Environment as TwigEnvironment;

class EmailManager
{
    private $mailer;
    private $templating;
    private $mjml;
    private $translator;
    private $settingsManager;
    private $transactionalAddress;

    public function __construct(
        \Swift_Mailer $mailer,
        TwigEnvironment $templating,
        RendererInterface $mjml,
        TranslatorInterface $translator,
        SettingsManager $settingsManager,
        $transactionalAddress)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->mjml = $mjml;
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
        $message = new \Swift_Message($subject);

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
        // FIXME Filter array instead
        foreach ($message->getTo() as $address => $name) {
            if (1 === preg_match('/demo\.coopcycle\.org$/', $address)) {
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
        $body = $this->mjml->render($this->templating->render('@App/emails/order/created.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderCreatedMessageForOwner(OrderInterface $order)
    {
        $subject = $this->translator->trans(
            'owner.order.created.subject',
            ['%order.number%' => $order->getNumber()],
            'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/order/created.mjml.twig', [
            'order' => $order,
            'is_owner' => true
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCreatedMessageForAdmin(OrderInterface $order)
    {
        $subject = $this->translator->trans('admin.order.created.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/order/created.mjml.twig', [
            'order' => $order,
            'is_admin' => true
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderPaymentMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.payment.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/order/payment.mjml.twig', [
            'order' => $order
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCancelledMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.cancelled.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/order/cancelled.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderAcceptedMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.accepted.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/order/accepted.mjml.twig', [
            'order' => $order,
        ]));
        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createTaskCompletedMessage(Task $task)
    {
        $key = $task->isDone() ? 'task.done.subject' : 'task.failed.subject';

        $subject = $this->translator->trans($key, ['%task.id%' => $task->getId()], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/task/completed.mjml.twig', [
            'task' => $task,
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderDelayedMessage(OrderInterface $order, $delay = 10)
    {
        $subject = $this->translator->trans('order.delayed.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/order/delayed.mjml.twig', [
            'order' => $order,
            'delay' => $delay
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createUserPledgeConfirmationMessage(Pledge $pledge)
    {
        $subject = $this->translator->trans('user.pledge.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/pledge/user_pledge.mjml.twig', [
            'pledge' => $pledge
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createAdminPledgeConfirmationMessage(Pledge $pledge)
    {
        $subject = $this->translator->trans('admin.pledge.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/pledge/admin_pledge.mjml.twig', [
            'pledge' => $pledge
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createInvitationMessage(Invitation $invitation)
    {
        $subject = $this->translator->trans('admin.send_invitation.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('@App/emails/admin_send_invitation.mjml.twig', [
            'user' => $invitation->getUser(),
            'invitation' => $invitation,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }
}
