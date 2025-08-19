<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Urbantz\Delivery;
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

        [$title, $body] = $message->parseTitleAndBodyForPushNotification($delivery, $this->translator);

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
}
