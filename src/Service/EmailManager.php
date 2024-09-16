<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\Invitation;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Entity\Task;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
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
        MailerInterface $mailer,
        TwigEnvironment $templating,
        RendererInterface $mjml,
        TranslatorInterface $translator,
        SettingsManager $settingsManager,
        private LoggerInterface $logger,
        $transactionalAddress)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->mjml = $mjml;
        $this->translator = $translator;
        $this->settingsManager = $settingsManager;
        $this->transactionalAddress = $transactionalAddress;
    }

    private function getFrom(): Address
    {
        return Address::fromString(
            sprintf('%s <%s>', $this->settingsManager->get('brand_name'), $this->transactionalAddress)
        );
    }

    private function getReplyTo(): Address
    {
        return Address::fromString(
            sprintf('%s <%s>', $this->settingsManager->get('brand_name'), $this->settingsManager->get('administrator_email'))
        );
    }

    public function createHtmlMessage($subject = null, $body = null): Email
    {
        $message = (new Email())
            ->subject($subject)
            ->sender($this->getFrom())
            ->from($this->getFrom());

        if ($body) {
            $message = $message->html($body);
        }

        $message->embedFromPath(__DIR__ . '/../../web/img/logo.png', 'logo', 'image/png');

        return $message;
    }

    public function createHtmlMessageWithReplyTo($subject = null, $body = null): Email
    {
        // Allow replying to the administrator
        return $this->createHtmlMessage($subject, $body)
            ->replyTo($this->getReplyTo());
    }

    public function send(Email $message)
    {
        $addresses = [];
        foreach ($message->getTo() as $address) {
            if (1 === preg_match('/demo\.coopcycle\.org$/', $address->getAddress())) {
                continue;
            }
            $addresses[] = $address;
        }

        if (count($addresses) === 0) {
            return;
        }

        $message->to(...$addresses);

        try {
            $this->mailer->send($message);
        } catch(\Exception $e) {
            $this->logger->error(sprintf("Failed to send email: %s", $e->getMessage()));
        }
    }

    /**
     * @param Email $message
     * @param Address|string ...$to
     */
    public function sendTo(Email $message, ...$to)
    {
        $this->send($message->to(...$to));
    }

    public function createOrderCreatedMessageForCustomer(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.created.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/created.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderCreatedMessageForOwner(OrderInterface $order, LocalBusiness $restaurant)
    {
        $subject = $this->translator->trans(
            'owner.order.created.subject',
            ['%order.number%' => $order->getNumber()],
            'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/created_for_owner.mjml.twig', [
            'order' => $order,
            'restaurant' => $restaurant,
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCreatedMessageForAdmin(OrderInterface $order)
    {
        $subject = $this->translator->trans('admin.order.created.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/created.mjml.twig', [
            'order' => $order,
            'is_admin' => true
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderPaymentMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.payment.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/payment.mjml.twig', [
            'order' => $order
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCancelledMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.cancelled.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/cancelled.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderAcceptedMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.accepted.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/accepted.mjml.twig', [
            'order' => $order,
        ]));
        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createTaskCompletedMessage(Task $task)
    {
        $key = sprintf('task.%s.%s.subject', strtolower($task->getType()), $task->isDone() ? 'done' : 'failed');

        $subject = $this->translator->trans($key, ['%id%' => $task->getDelivery()->getId()], 'emails');

        $body = $this->mjml->render($this->templating->render('emails/task/completed.mjml.twig', [
            'task' => $task,
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderDelayedMessage(OrderInterface $order, $delay = 10)
    {
        $subject = $this->translator->trans('order.delayed.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/delayed.mjml.twig', [
            'order' => $order,
            'delay' => $delay
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createUserPledgeConfirmationMessage(Pledge $pledge)
    {
        $subject = $this->translator->trans('user.pledge.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/pledge/user_pledge.mjml.twig', [
            'pledge' => $pledge
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createAdminPledgeConfirmationMessage(Pledge $pledge)
    {
        $subject = $this->translator->trans('admin.pledge.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/pledge/admin_pledge.mjml.twig', [
            'pledge' => $pledge
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createInvitationMessage(Invitation $invitation)
    {
        $subject = $this->translator->trans('admin.send_invitation.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/admin_send_invitation.mjml.twig', [
            'user' => $invitation->getUser(),
            'invitation' => $invitation,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createBusinessAccountInvitationMessage(Invitation $invitation, BusinessAccount $account)
    {
        $subject = $this->translator->trans('admin.send_invitation.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/business_account_send_invitation.mjml.twig', [
            'user' => $invitation->getUser(),
            'invitation' => $invitation,
            'account' => $account,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    private function createExpiringAuthorizationReminderMessage(OrderInterface $order, $isAdmin = false)
    {
        $subject = $this->translator->trans('order.expiring_authorization.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/expiring_authorization.mjml.twig', [
            'order' => $order,
            'is_admin' => $isAdmin,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createExpiringAuthorizationReminderMessageForAdmin(OrderInterface $order)
    {
        return $this->createExpiringAuthorizationReminderMessage($order, true);
    }

    public function createExpiringAuthorizationReminderMessageForOwner(OrderInterface $order)
    {
        return $this->createExpiringAuthorizationReminderMessage($order, false);
    }

    public function createOrderReceiptMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.receipt.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/receipt.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderPaymentFailedMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.payment_failed.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/payment_failed.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createAdhocOrderMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.created.subject', ['%order.number%' => $order->getNumber()], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/adhoc.mjml.twig', [
            'order' => $order
        ]));

        return $this->createHtmlMessage($subject, $body);
    }
}
