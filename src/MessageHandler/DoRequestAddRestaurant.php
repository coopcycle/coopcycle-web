<?php
declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Message\Request\RequestRestaurant;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DoRequestAddRestaurant implements MessageSubscriberInterface
{
    /**
     * @var EmailManager
     */
    private EmailManager $emailManager;
    /**
     * @var TranslatorInterface
     */
    private TranslatorInterface $translator;
    /**
     * @var Environment
     */
    private Environment $twig;
    /**
     * @var SettingsManager
     */
    private SettingsManager $settingsManager;

    public function __construct(
        EmailManager $emailManager,
        TranslatorInterface $translator,
        Environment $twig,
        SettingsManager $settingsManager
    ) {
        $this->emailManager = $emailManager;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->settingsManager = $settingsManager;
    }

    public function __invoke(
        RequestRestaurant $requestRestaurant
    ) {

        $message = new \Swift_Message(
            $this->translator->trans('request.restaurant.email.subject'),
            $this->twig->render('emails/request/restaurant.mjml.twig', [
                'restaurant' => $requestRestaurant,
            ])
        );
        $this->emailManager->sendTo($message, $this->settingsManager->get('administrator_email'));
    }

    public static function getHandledMessages(): iterable
    {
        yield RequestRestaurant::class;
    }
}
