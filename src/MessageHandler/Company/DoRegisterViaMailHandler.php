<?php
declare(strict_types=1);

namespace AppBundle\MessageHandler\Company;

use AppBundle\Message\Company\RequestRegistration;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DoRegisterViaMailHandler implements MessageSubscriberInterface
{
    /**
     * @var EmailManager
     */
    private EmailManager $emailManager;
    /**
     * @var SettingsManager
     */
    private SettingsManager $settingsManager;
    /**
     * @var TranslatorInterface
     */
    private TranslatorInterface $translator;
    /**
     * @var Environment
     */
    private Environment $twig;

    public function __construct(
        EmailManager $emailManager,
        SettingsManager $settingsManager,
        TranslatorInterface $translator,
        Environment $twig
    ) {
        $this->emailManager = $emailManager;
        $this->settingsManager = $settingsManager;
        $this->translator = $translator;
        $this->twig = $twig;
    }

    public function __invoke(RequestRegistration $registration)
    {
        $message = new \Swift_Message(
            $this->translator->trans('registration.company', [], 'emails'),
            $this->twig->render('emails/company/request_registration.mjml.twig', [
                'registration' => $registration
            ])
        );
        $this->emailManager->sendTo($message, $this->settingsManager->get('administrator_email'));
    }

    public static function getHandledMessages(): iterable
    {
        yield RequestRegistration::class;
    }
}
