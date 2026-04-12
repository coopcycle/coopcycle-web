<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\BusinessAccount;
use AppBundle\Entity\Invitation;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Restaurant\Pledge;
use AppBundle\Entity\Task;
use AppBundle\LoopEat\Context as LoopeatContext;
use AppBundle\LoopEat\ContextInitializer as LoopeatContextInitializer;
use Hashids\Hashids;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private LoopeatContextInitializer $loopeatContextInitializer,
        private LoopeatContext $loopeatContext,
        private LoggerInterface $logger,
        $transactionalAddress,
        private EmailTemplateManager $emailTemplateManager,
        private UrlGeneratorInterface $urlGenerator,
        private Hashids $hashids16,
        private RequestStack $requestStack)
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
        return Address::create(
            sprintf('%s <%s>', $this->settingsManager->get('brand_name'), $this->transactionalAddress)
        );
    }

    private function getReplyTo(): Address
    {
        return Address::create(
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
     * @param Address|string ...$to
     */
    public function sendTo(Email $message, ...$to)
    {
        $this->send($message->to(...$to));
    }

    private function currentLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';
    }

    /**
     * Renders a custom MJML template stored in S3, substituting variables.
     * Uses the current request locale with fallback to English.
     * Returns null if no custom template is configured for this type in any fallback locale.
     */
    /**
     * Renders a Twig MJML template, resolves slot markers, then compiles to HTML.
     *
     * @param array<string,string> $slots Map of slot name → pre-rendered MJML snippet
     */
    private function renderTwigMjml(string $template, array $context, array $slots = []): string
    {
        $mjml = $this->templating->render($template, $context);
        if (!empty($slots)) {
            $mjml = $this->emailTemplateManager->resolveSlots($mjml, $slots);
        }
        return $this->mjml->render($mjml);
    }

    /**
     * @param array<string,string> $slots Map of slot name → pre-rendered MJML snippet
     */
    private function renderCustom(string $type, array $variables, array $slots = []): ?string
    {
        $mjml = $this->emailTemplateManager->renderCustomTemplate(
            $type,
            array_merge(['brand_name' => $this->settingsManager->get('brand_name')], $variables),
            $this->currentLocale()
        );

        if ($mjml === null) {
            return null;
        }

        if (!empty($slots)) {
            $mjml = $this->emailTemplateManager->resolveSlots($mjml, $slots);
        }

        return $this->mjml->render($mjml);
    }

    private function orderUrl(OrderInterface $order): string
    {
        return $this->urlGenerator->generate(
            'order_confirm',
            ['hashid' => $this->hashids16->encode($order->getId())],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function createOrderCreatedMessageForCustomer(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.created.subject', ['{{order_number}}' => $order->getNumber()], 'emails');

        $orderItemsSlot = ['order_items' => $this->templating->render('emails/order/_partials/items.mjml.twig', ['order' => $order])];

        $body = $this->renderCustom('order_created', [
            'order_number' => $order->getNumber(),
            'order_url'    => $this->orderUrl($order),
        ], $orderItemsSlot) ?? $this->renderTwigMjml('emails/order/created.mjml.twig', ['order' => $order], $orderItemsSlot);

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderCreatedMessageForOwner(OrderInterface $order, LocalBusiness $restaurant)
    {
        $subject = $this->translator->trans(
            'owner.order.created.subject',
            ['{{order_number}}' => $order->getNumber()],
            'emails');
        $body = $this->renderTwigMjml('emails/order/created_for_owner.mjml.twig', [
            'order'      => $order,
            'restaurant' => $restaurant,
        ], [
            'order_items' => $this->templating->render('emails/order/_partials/items.mjml.twig', ['order' => $order]),
        ]);

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCreatedMessageForAdmin(OrderInterface $order)
    {
        $subject = $this->translator->trans('admin.order.created.subject', [], 'emails');
        $body = $this->mjml->render($this->templating->render('emails/order/created_for_admin.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderPaymentMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.payment.subject', ['{{order_number}}' => $order->getNumber()], 'emails');

        $orderItemsSlot = ['order_items' => $this->templating->render('emails/order/_partials/items.mjml.twig', ['order' => $order])];

        $body = $this->renderCustom('order_payment', [
            'order_number' => $order->getNumber(),
        ], $orderItemsSlot) ?? $this->renderTwigMjml('emails/order/payment.mjml.twig', ['order' => $order], $orderItemsSlot);

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderCancelledMessage(OrderInterface $order)
    {
        $subject = $this->translator->trans('order.cancelled.subject', ['{{order_number}}' => $order->getNumber()], 'emails');

        $body = $this->renderCustom('order_cancelled', [
            'order_number' => $order->getNumber(),
        ]) ?? $this->mjml->render($this->templating->render('emails/order/cancelled.mjml.twig', [
            'order' => $order,
        ]));

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createOrderAcceptedMessage(OrderInterface $order)
    {
        if ($order->isLoopeat()) {
            $this->loopeatContextInitializer->initialize($order, $this->loopeatContext);
        }

        $subject = $this->translator->trans('order.accepted.subject', ['{{order_number}}' => $order->getNumber()], 'emails');

        $loopeatSlot = $order->isLoopeat()
            ? $this->templating->render('emails/order/_partials/loopeat_info.mjml.twig', ['order' => $order])
            : '';

        $body = $this->renderCustom('order_accepted', [
            'order_number' => $order->getNumber(),
            'order_url'    => $this->orderUrl($order),
        ], ['loopeat_info' => $loopeatSlot])
            ?? $this->renderTwigMjml('emails/order/accepted.mjml.twig', ['order' => $order], ['loopeat_info' => $loopeatSlot]);

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

    public function createTaskCompletedMessage(Task $task)
    {
        $key = sprintf('task.%s.%s.subject', strtolower($task->getType()), $task->isDone() ? 'done' : 'failed');

        $subject = $this->translator->trans($key, ['{{id}}' => $task->getDelivery()->getId()], 'emails');

        $trackingUrl = $this->urlGenerator->generate(
            'dashboard_delivery',
            ['id' => $task->getDelivery()->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $body = $this->renderCustom('task_completed', [
            'delivery_id'  => $task->getDelivery()->getId(),
            'tracking_url' => $trackingUrl,
        ]) ?? $this->mjml->render($this->templating->render('emails/task/completed.mjml.twig', [
            'task' => $task,
        ]));

        return $this->createHtmlMessage($subject, $body);
    }

    public function createOrderDelayedMessage(OrderInterface $order, $delay = 10)
    {
        $subject = $this->translator->trans('order.delayed.subject', ['{{order_number}}' => $order->getNumber()], 'emails');

        $body = $this->renderCustom('order_delayed', [
            'order_number' => $order->getNumber(),
            'delay'        => $delay,
        ]) ?? $this->mjml->render($this->templating->render('emails/order/delayed.mjml.twig', [
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
        $subject = $this->translator->trans('order.expiring_authorization.subject', ['{{order_number}}' => $order->getNumber()], 'emails');
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
        $subject = $this->translator->trans('order.receipt.subject', ['{{order_number}}' => $order->getNumber()], 'emails');

        $orderItemsSlot = ['order_items' => $this->templating->render('emails/order/_partials/items.mjml.twig', ['order' => $order])];

        $body = $this->renderCustom('order_receipt', [
            'order_number' => $order->getNumber(),
        ], $orderItemsSlot) ?? $this->renderTwigMjml('emails/order/receipt.mjml.twig', ['order' => $order], $orderItemsSlot);

        return $this->createHtmlMessageWithReplyTo($subject, $body);
    }

}
