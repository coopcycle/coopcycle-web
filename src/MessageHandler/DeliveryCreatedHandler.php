<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Message\DeliveryCreated;
use AppBundle\Message\Email;
use AppBundle\Message\PushNotificationV2;
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

        $tasks = $delivery->getTasks();
        $order = $delivery->getOrder();
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        $puafdt = $pickup->getAfter()->format('H:i');
        $pubfdt = $pickup->getBefore()->format('H:i');
        $doafdt = $dropoff->getAfter()->format('H:i');
        $dobfdt = $dropoff->getBefore()->format('H:i');
        $date = $pickup->getAfter()->format('Y-m-d H:i');
        $dateLocal = Carbon::instance($pickup->getAfter())
            ->locale($this->locale)
            ->calendar();

        $ownerIsPickupAddr = $delivery->getOwner()->getAddress()->getStreetAddress() === $pickup->getAddress()->getStreetAddress();
        $title = $delivery->getOwner()->getName();
        $body = $this->translator->trans('notifications.tap_to_open');
        // Translate the ones below if needed/wanted
        $PU = "PU";
        $PUs = "PUs";
        $DO = "DO";
        $DOs = "DOs";
        $pickups_str = "pickups";
        $dropoffs_str = "dropoffs";

        if ($order && $order->isFoodtech()) {
            $title .= " -> " . $order->getShippingAddress()->getStreetAddress();
            $body = $PU. ": " . $puafdt . " | " . $DO . ": " . $doafdt;
        } else {
            $title .= " -> ";
            switch (Delivery::getType($tasks)) {
                case Delivery::TYPE_SIMPLE:
                    $body = $PU. ": " . $puafdt . "-" . $pubfdt . " | " . $DO . ": " . $doafdt . "-" . $dobfdt;
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
                    $title = count($pickups) . " " . $pickups_str . " -> ";
                    $firstPickup = $pickups[0];
                    $lastPickup = $pickups[ count($pickups) - 1 ];
                    $puafdt = $firstPickup->getAfter()->format('H:i');
                    $pubfdt = $lastPickup->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple PUs
                    $body = $PUs. ": " . $puafdt . "-" . $pubfdt . " | " . $DO . ": " . $doafdt . "-" . $dobfdt;
                    foreach ($pickups as $pickup) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        $afdt = $pickup->getAfter()->format('H:i');
                        $bfdt = $pickup->getBefore()->format('H:i');
                        $body .= "\n" . $PU . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                    $title .= $ttitle;
                    $body .= $tbody ? "\n" . $DO . ": " . $tbody : '';
                    break;
                case Delivery::TYPE_MULTI_DROPOFF:
                    $dropoffs = array_values(array_filter($tasks, fn($t) => $t->isDropoff()));
                    $title .= count($dropoffs) . " " . $dropoffs_str;
                    $firstDropoff = $dropoffs[0];
                    $lastDropoff = $dropoffs[ count($dropoffs) - 1 ];
                    $doafdt = $firstDropoff->getAfter()->format('H:i');
                    $dobfdt = $lastDropoff->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple DOs
                    $body = $PU. ": " . $puafdt . "-" . $pubfdt . " | " . $DOs . ": " . $doafdt . "-" . $dobfdt;
                    if (!$ownerIsPickupAddr) { // Pickup address is not the owner address
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        $body .= "\n" . $PU . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    foreach ($dropoffs as $dropoff) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                        $afdt = $dropoff->getAfter()->format('H:i');
                        $bfdt = $dropoff->getBefore()->format('H:i');
                        $body .= "\n" . $DO . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    break;
                case Delivery::TYPE_MULTI_MULTI:
                    $pickups = array_values(array_filter($tasks, fn($t) => $t->isPickup()));
                    $dropoffs = array_values(array_filter($tasks, fn($t) => $t->isDropoff()));
                    $title = count($pickups) . " " . $pickups_str . " -> " . count($dropoffs) . " " . $dropoffs_str;
                    $firstPickup = $pickups[0];
                    $lastPickup = $pickups[ count($pickups) - 1 ];
                    $firstDropoff = $dropoffs[0];
                    $lastDropoff = $dropoffs[ count($dropoffs) - 1 ];
                    $puafdt = $firstPickup->getAfter()->format('H:i');
                    $pubfdt = $lastPickup->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple PUs
                    $doafdt = $firstDropoff->getAfter()->format('H:i');
                    $dobfdt = $lastDropoff->getAfter()->format('H:i'); // Use last's "after" as "before" for multiple DOs
                    $body = $PUs. ": " . $puafdt . "-" . $pubfdt . " | " . $DOs . ": " . $doafdt . "-" . $dobfdt;
                    foreach ($pickups as $pickup) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        $afdt = $pickup->getAfter()->format('H:i');
                        $bfdt = $pickup->getBefore()->format('H:i');
                        $body .= "\n" . $PU . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    foreach ($dropoffs as $dropoff) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                        $afdt = $dropoff->getAfter()->format('H:i');
                        $bfdt = $dropoff->getBefore()->format('H:i');
                        $body .= "\n" . $DO . " " . $afdt . "-" . $bfdt . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    break;
            }
        }

        $message = $this->translator->trans('notifications.delivery_created', ['%date%' => strtolower($dateLocal)]);

        $users = $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER']);

        $data = [
            'delivery_id' => $delivery->getId(),
            'order_id' => $order ? $order->getId() : null,
            'date' => $date,
            'date_local' => $dateLocal
        ];

        $this->messageBus->dispatch(
            new PushNotificationV2($title, $body, $users, $data)
        );

        $adminEmail = $this->settingsManager->get('administrator_email');

        if (!$adminEmail) {
            return;
        }

        $body = $this->mjml->render($this->twig->render('emails/delivery/created.mjml.twig', [
            'body'     => $message,
            'delivery' => $delivery
        ]));

        $emailMessage = $this->emailManager->createHtmlMessage($message, $body);

        $this->messageBus->dispatch(
            new Email($emailMessage, $adminEmail)
        );
    }

    private function getTaskAddressTitleAndBody(Task $task): array
    {
        $taskaddr = $task->getAddress();
        $title = $taskaddr->getName() ?: $taskaddr->getStreetAddress();
        $body = $taskaddr->getName() ? $taskaddr->getStreetAddress() : '';

        return [$title, $body];
    }
}
