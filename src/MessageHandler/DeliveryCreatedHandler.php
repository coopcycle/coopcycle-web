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

        $title = $delivery->getOwner()->getName();
        $body = $this->translator->trans('notifications.tap_to_open');

        if ($order && $order->isFoodtech()) {
            $title .= "->" . $order->getShippingAddress()->getStreetAddress();
            // TODO: Translate PU and DO
            $body = "PU: " . $puafdt . " | DO: " . $doafdt;
        } else {
            $title .= "->";
            switch (Delivery::getType($tasks)) {
                case Delivery::TYPE_SIMPLE:
                    // TODO: Translate..!
                    $body = "PU: " . $puafdt . "-" . $pubfdt . " | DO: " . $doafdt . "-" . $dobfdt;
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                    $title .= $ttitle;
                    // TODO: Translate..!
                    $body .= $tbody ? "\nDO: " . $tbody : '';
                    break;
                case Delivery::TYPE_MULTI_PICKUP:
                    $pickups = array_filter($tasks, fn($t) => $t->isPickup());
                    $title = count($pickups) . " pickups->";
                    $lastPickup = $pickups[ count($pickups) - 1 ];
                    $pubfdt = $lastPickup->getBefore()->format('H:i');
                    // TODO: Translate..!
                    $body = "PUs: " . $puafdt . "-" . $pubfdt . " | DO: " . $doafdt . "-" . $dobfdt;
                    foreach ($pickups as $idx => $pickup) {
                        [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($pickup);
                        // TODO: Translate..!
                        $body .= "\nPU " . ($idx+1) . ": " . $ttitle . ($tbody ? " (" . $tbody . ")" : '');
                    }
                    [$ttitle, $tbody] = $this->getTaskAddressTitleAndBody($dropoff);
                    $title .= $ttitle;
                    // TODO: Translate..!
                    $body .= $tbody ? "\nDO: " . $tbody : '';
                    break;
                case Delivery::TYPE_MULTI_DROPOFF:
                    $dropoffs = array_filter($tasks, fn($t) => $t->isDropoff());
                    break;
                case Delivery::TYPE_MULTI_MULTI:
                    $pickups = array_filter($tasks, fn($t) => $t->isPickup());
                    $dropoffs = array_filter($tasks, fn($t) => $t->isDropoff());
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
