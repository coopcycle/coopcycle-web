<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Message\DeliveryCreated;
use AppBundle\Message\Email;
use AppBundle\Message\PushNotification;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

#[AsMessageHandler]
class DeliveryCreatedHandler
{
    private $entityManager;
    private $userManager;
    private $emailManager;
    private $mjml;
    private $messageBus;
    private $translator;
    private $twig;
    private $settingsManager;
    private $locale;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserManagerInterface $userManager,
        EmailManager $emailManager,
        RendererInterface $mjml,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator,
        TwigEnvironment $twig,
        SettingsManager $settingsManager,
        string $locale)
    {
        $this->entityManager = $entityManager;
        $this->userManager = $userManager;
        $this->emailManager = $emailManager;
        $this->mjml = $mjml;
        $this->messageBus = $messageBus;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->settingsManager = $settingsManager;
        $this->locale = $locale;
    }

    public function __invoke(DeliveryCreated $message)
    {
        // TODO Log activity?

        $delivery = $this->entityManager->getRepository(Delivery::class)->find($message->getDeliveryId());
        if (!$delivery) {
            return;
        }

        $order = $delivery->getOrder();
        $pickup = $delivery->getPickup();
        $date = $pickup->getAfter()->format('Y-m-d H:i');
        $dateLocal = Carbon::instance($pickup->getAfter())
            ->locale($this->locale)
            ->calendar();

        [$title, $body] = $this->parseTitleAndBodyForPushNotification($delivery);

        $users = $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER']);

        $data = [
            'event' => 'delivery:created',
            'delivery_id' => $delivery->getId(),
            'order_id' => $order ? $order->getId() : null,
            'date' => $date,
            'date_local' => $dateLocal
        ];

        $this->messageBus->dispatch(
            new PushNotification($title, $body, $users, $data)
        );

        $adminEmail = $this->settingsManager->get('administrator_email');

        if (!$adminEmail) {
            return;
        }

        $message = $this->translator->trans('notifications.delivery_created', ['%date%' => strtolower($dateLocal)]);
        $body = $this->mjml->render($this->twig->render('emails/delivery/created.mjml.twig', [
            'body'     => $message,
            'delivery' => $delivery
        ]));

        $emailMessage = $this->emailManager->createHtmlMessage($message, $body);

        $this->messageBus->dispatch(
            new Email($emailMessage, $adminEmail)
        );
    }

    // It's public just to be testeable
    public function parseTitleAndBodyForPushNotification(Delivery $delivery): array
    {
        $tasks = $delivery->getTasks();
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        $pickupAfter = $pickup->getAfter()->format('H:i');
        $pickupBefore = $pickup->getBefore()->format('H:i');
        $dropoffAfter = $dropoff->getAfter()->format('H:i');
        $dropoffBefore = $dropoff->getBefore()->format('H:i');

        $ownerIsPickupAddr = $delivery->getOwner()->getAddress()->getStreetAddress() === $pickup->getAddress()->getStreetAddress();
        $title = $delivery->getOwner()->getName() . " -> ";
        $body = $this->translator->trans('notifications.tap_to_open');
        // Translate the ones below if needed/wanted
        $PU = "PU";
        $PUs = "PUs";
        $DO = "DO";
        $DOs = "DOs";
        $pickupsStr = "pickups";
        $dropoffsStr = "dropoffs";

        switch (Delivery::getType($tasks)) {
            case Delivery::TYPE_SIMPLE:
                $body = $PU. ": " . $pickupAfter . "-" . $pickupBefore . " | " . $DO . ": " . $dropoffAfter . "-" . $dropoffBefore;
                if (!$ownerIsPickupAddr) { // Pickup address is not the owner address
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                    $body .= "\n" . $PU . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                }
                [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                $title .= $ttitle;
                $body .= $tbody ? "\n" . $DO . ": " . $tbody : '';
                break;
            case Delivery::TYPE_MULTI_PICKUP:
                $pickups = array_values(array_filter($tasks, fn($t) => $t->isPickup()));
                $title = count($pickups) . " " . $pickupsStr . " -> ";
                $firstPickup = $pickups[0];
                $lastPickup = $pickups[ count($pickups) - 1 ];
                $pickupAfter = $firstPickup->getAfter()->format('H:i');
                $pickupBefore = $lastPickup->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple PUs
                $body = $PUs. ": " . $pickupAfter . "-" . $pickupBefore . " | " . $DO . ": " . $dropoffAfter . "-" . $dropoffBefore;
                foreach ($pickups as $pickup) {
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                    $after = $pickup->getAfter()->format('H:i');
                    $before = $pickup->getBefore()->format('H:i');
                    $body .= "\n" . $PU . " " . $after . "-" . $before . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                }
                [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                $title .= $ttitle;
                $body .= $tbody ? "\n" . $DO . ": " . $tbody : '';
                break;
            case Delivery::TYPE_MULTI_DROPOFF:
                $dropoffs = array_values(array_filter($tasks, fn($t) => $t->isDropoff()));
                $title .= count($dropoffs) . " " . $dropoffsStr;
                $firstDropoff = $dropoffs[0];
                $lastDropoff = $dropoffs[ count($dropoffs) - 1 ];
                $dropoffAfter = $firstDropoff->getAfter()->format('H:i');
                $dropoffBefore = $lastDropoff->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple DOs
                $body = $PU. ": " . $pickupAfter . "-" . $pickupBefore . " | " . $DOs . ": " . $dropoffAfter . "-" . $dropoffBefore;
                if (!$ownerIsPickupAddr) { // Pickup address is not the owner address
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                    $body .= "\n" . $PU . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                }
                foreach ($dropoffs as $dropoff) {
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                    $after = $dropoff->getAfter()->format('H:i');
                    $before = $dropoff->getBefore()->format('H:i');
                    $body .= "\n" . $DO . " " . $after . "-" . $before . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                }
                break;
            case Delivery::TYPE_MULTI_MULTI:
                $pickups = array_values(array_filter($tasks, fn($t) => $t->isPickup()));
                $dropoffs = array_values(array_filter($tasks, fn($t) => $t->isDropoff()));
                $title = count($pickups) . " " . $pickupsStr . " -> " . count($dropoffs) . " " . $dropoffsStr;
                $firstPickup = $pickups[0];
                $lastPickup = $pickups[ count($pickups) - 1 ];
                $firstDropoff = $dropoffs[0];
                $lastDropoff = $dropoffs[ count($dropoffs) - 1 ];
                $pickupAfter = $firstPickup->getAfter()->format('H:i');
                $pickupBefore = $lastPickup->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple PUs
                $dropoffAfter = $firstDropoff->getAfter()->format('H:i');
                $dropoffBefore = $lastDropoff->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple DOs
                $body = $PUs. ": " . $pickupAfter . "-" . $pickupBefore . " | " . $DOs . ": " . $dropoffAfter . "-" . $dropoffBefore;
                foreach ($pickups as $pickup) {
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                    $after = $pickup->getAfter()->format('H:i');
                    $before = $pickup->getBefore()->format('H:i');
                    $body .= "\n" . $PU . " " . $after . "-" . $before . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                }
                foreach ($dropoffs as $dropoff) {
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                    $after = $dropoff->getAfter()->format('H:i');
                    $before = $dropoff->getBefore()->format('H:i');
                    $body .= "\n" . $DO . " " . $after . "-" . $before . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                }
                break;
        }

        return [$title, $body];
    }

    private function getTaskAddressTitleAndBody(Task $task): array
    {
        $taskaddr = $task->getAddress();
        $title = $taskaddr->getName() ?: $taskaddr->getStreetAddress();
        $body = $taskaddr->getName() ? $taskaddr->getStreetAddress() : '';

        return [$title, $body];
    }
}
