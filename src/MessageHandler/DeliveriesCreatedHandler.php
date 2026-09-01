<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Message\DeliveriesCreated;
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
 * Sends a *single* recap notification (push & email) for several deliveries created at once,
 * i.e when generating orders from recurrence rules.
 *
 * @see \AppBundle\Service\DeliveryCreatedNotifier
 */
#[AsMessageHandler]
class DeliveriesCreatedHandler
{
    private $logger;

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

    public function __invoke(DeliveriesCreated $message)
    {
        $deliveryIds = $message->getDeliveryIds();

        $this->logger->info(sprintf("[%s]: %d deliveries created", get_class($this), count($deliveryIds)));

        $deliveries = $this->entityManager->getRepository(Delivery::class)
            ->findBy(['id' => $deliveryIds]);

        if (0 === count($deliveries)) {
            $this->logger->error(sprintf(
                "[%s]: No delivery found, skipping notification",
                get_class($this)
            ));
            return;
        }

        // Keep the deliveries sorted by pickup date, as they are listed in the notifications
        usort($deliveries, fn (Delivery $a, Delivery $b) =>
            $a->getPickup()->getAfter() <=> $b->getPickup()->getAfter());

        $recap = $this->translateRecap($deliveries);

        $users = $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER']);

        $data = [
            'event' => [
                'name' => 'deliveries:created'
            ],
            'task_ids' => array_merge(...array_map(
                fn(Delivery $delivery) => array_map(fn(Task $t) => $t->getId(), $delivery->getTasks()),
                $deliveries
            )),
            'delivery_ids' => array_map(fn(Delivery $delivery) => $delivery->getId(), $deliveries),
        ];

        if (null !== ($date = $this->getCommonDate($deliveries))) {
            $data['date'] = $date->format('Y-m-d');
        }

        $this->messageBus->dispatch(
            new PushNotification($recap, $this->translator->trans('notifications.tap_to_open'), $users, $data)
        );

        $adminEmail = $this->settingsManager->get('administrator_email');

        if (!$adminEmail) {
            $this->logger->error(sprintf(
                "[%s]: Admin email not found, skipping notification",
                get_class($this)
            ));
            return;
        }

        $body = $this->mjml->render($this->twig->render('emails/delivery/created_recap.mjml.twig', [
            'body'       => $recap,
            'deliveries' => $deliveries,
        ]));

        $emailMessage = $this->emailManager->createHtmlMessage($recap, $body);

        $this->emailManager->sendTo($emailMessage, $adminEmail);
    }

    /**
     * @param Delivery[] $deliveries
     */
    private function translateRecap(array $deliveries): string
    {
        $date = $this->getCommonDate($deliveries);

        if (null === $date) {
            return $this->translator->trans('notifications.deliveries_created.no_date', [
                '%count%' => count($deliveries),
            ]);
        }

        return $this->translator->trans('notifications.deliveries_created', [
            '%count%' => count($deliveries),
            '%date%'  => strtolower(LocalizedDate::format($date, $this->locale)),
        ]);
    }

    /**
     * Returns the pickup date when all the deliveries are to be picked up the same day, null otherwise.
     *
     * @param Delivery[] $deliveries
     */
    private function getCommonDate(array $deliveries): ?\DateTimeInterface
    {
        $date = null;

        foreach ($deliveries as $delivery) {
            $after = $delivery->getPickup()->getAfter();

            if (null === $date) {
                $date = $after;
                continue;
            }

            if ($date->format('Y-m-d') !== $after->format('Y-m-d')) {
                return null;
            }
        }

        return $date;
    }
}
