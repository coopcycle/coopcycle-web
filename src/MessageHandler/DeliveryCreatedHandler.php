<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
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
use Symfony\Component\Security\Core\User\UserInterface;
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
        $date = Carbon::instance($delivery->getPickup()->getAfter())
            ->locale($this->locale)
            ->calendar();

        if (($order = $delivery->getOrder())) {
            if ($order->isFoodtech()) {

            } else {
                switch ($delivery->getType($tasks)) {
                    case Delivery::TYPE_SIMPLE:
                        break;
                    case Delivery::TYPE_MULTI_PICKUP:
                        break;
                    case Delivery::TYPE_MULTI_DROPOFF:
                        break;
                    case Delivery::TYPE_MULTI_MULTI:
                        break;
                }
            }
        }

        $message = $this->translator->trans('notifications.delivery_created', ['%date%' => strtolower($date)]);
        //$message = "This is a test!\nWith a line break! This could be larger hmmm will see..?\nAnd with another line break?!";

        $users = $this->userManager->findUsersByRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER']);
        $usernames = array_map(fn(UserInterface $user) => $user->getUsername(), $users);

        $this->messageBus->dispatch(
            new PushNotification($message, $usernames)
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
}
