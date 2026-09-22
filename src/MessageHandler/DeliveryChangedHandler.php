<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Message\DeliveryCancelled;
use AppBundle\Message\DeliveryUpdated;
use AppBundle\Message\PushNotification;
use AppBundle\Security\UserManager;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Utils\LocalizedDate;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

/**
 * Notifies admins & dispatchers (push & email)
 * when a delivery has been modified or cancelled by a store owner.
 *
 * @see \AppBundle\MessageHandler\DeliveryCreatedHandler
 */
class DeliveryChangedHandler
{
    private LoggerInterface $logger;

    /**
     * @param UserManager $userManager
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserManagerInterface $userManager,
        private EmailManager $emailManager,
        private RendererInterface $mjml,
        private MessageBusInterface $messageBus,
        private TranslatorInterface $translator,
        private TwigEnvironment $twig,
        private SettingsManager $settingsManager,
        private string $locale,
        ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    #[AsMessageHandler]
    public function onDeliveryUpdated(DeliveryUpdated $message): void
    {
        $this->notify($message->getDeliveryId(), 'updated');
    }

    #[AsMessageHandler]
    public function onDeliveryCancelled(DeliveryCancelled $message): void
    {
        $this->notify($message->getDeliveryId(), 'cancelled');
    }

    /**
     * @param string $change "updated" or "cancelled"
     */
    private function notify(int $deliveryId, string $change): void
    {
        $this->logger->info(sprintf("[%s]: Delivery %d %s", get_class($this), $deliveryId, $change));

        $delivery = $this->entityManager->getRepository(Delivery::class)->find($deliveryId);
        if (!$delivery) {
            $this->logger->error(sprintf(
                "[%s]: Delivery %d not found, skipping notification",
                get_class($this),
                $deliveryId
            ));
            return;
        }

        $order = $delivery->getOrder();
        $pickup = $delivery->getPickup();
        $date = $pickup->getAfter();
        $dateLocal = LocalizedDate::format($date, $this->locale);

        $store = $delivery->getStore();

        $title = $this->translator->trans(sprintf('notifications.delivery_%s', $change), [
            '%store%' => $store ? $store->getName() : '',
            '%date%' => strtolower($dateLocal),
        ]);
        $body = $order
            ? $this->translator->trans('notifications.delivery_changed.order', ['%number%' => $order->getNumber()])
            : $this->translator->trans('notifications.tap_to_open');

        $users = $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER']);

        $data = [
            'event' => [
                'name' => sprintf('delivery:%s', $change)
            ],
            'task_ids' => array_map(fn(Task $t) => $t->getId(), $delivery->getTasks()),
            'delivery_id' => $delivery->getId(),
            'order_id' => $order ? $order->getId() : null,
            'order_number' => $order ? $order->getNumber() : null,
            'date_local' => $dateLocal,
            'date' => $date->format('Y-m-d'),
            'time' => $date->format('H:i')
        ];

        $this->messageBus->dispatch(
            new PushNotification($title, $body, $users, $data)
        );

        $adminEmail = $this->settingsManager->get('administrator_email');

        if (!$adminEmail) {
            $this->logger->error(sprintf(
                "[%s]: Admin email not found, skipping notification",
                get_class($this)
            ));
            return;
        }

        $html = $this->mjml->render($this->twig->render('emails/delivery/changed.mjml.twig', [
            'body'     => $title,
            'delivery' => $delivery,
            'change'   => $change,
        ]));

        $emailMessage = $this->emailManager->createHtmlMessage($title, $html);

        $this->emailManager->sendTo($emailMessage, $adminEmail);
    }
}
