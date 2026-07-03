<?php

namespace AppBundle\Security\TwoFactor;

use AppBundle\Service\SettingsManager;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;

class EmailAuthCodeMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private TwigEnvironment $twig,
        private RendererInterface $mjml,
        private SettingsManager $settingsManager,
        private string $transactionalAddress,
    ) {}

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $brandName = $this->settingsManager->get('brand_name');
        $from = Address::create(sprintf('%s <%s>', $brandName, $this->transactionalAddress));

        $mjmlBody = $this->twig->render('security/2fa_code.mjml.twig', [
            'authCode' => $user->getEmailAuthCode(),
            'brandName' => $brandName,
        ]);
        $html = $this->mjml->render($mjmlBody);

        $email = (new Email())
            ->from($from)
            ->to($user->getEmailAuthRecipient())
            ->subject(sprintf('[%s] Your login verification code', $brandName))
            ->html($html);

        $this->mailer->send($email);
    }
}
