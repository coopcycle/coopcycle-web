<?php

namespace AppBundle\Mailer;

use FOS\UserBundle\Mailer\TwigSwiftMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class FOSUserBundleMailer extends TwigSwiftMailer
{
    /**
     * @var MailerInterface
     */
    protected $sfMailer;

    private $logging = false;

    private $messages = [];

    public function __construct(MailerInterface $mailer, UrlGeneratorInterface $router, Environment $twig, array $parameters)
    {
        $this->sfMailer = $mailer;
        $this->router = $router;
        $this->twig = $twig;
        $this->parameters = $parameters;
    }

    /**
     * @param string $templateName
     * @param array  $context
     * @param array  $fromEmail
     * @param string $toEmail
     */
    protected function sendMessage($templateName, $context, $fromEmail, $toEmail)
    {
        $template = $this->twig->load($templateName);
        $subject = $template->renderBlock('subject', $context);
        $textBody = $template->renderBlock('body_text', $context);

        $htmlBody = '';

        if ($template->hasBlock('body_html', $context)) {
            $htmlBody = $template->renderBlock('body_html', $context);
        }

        $from = [];
        foreach ($fromEmail as $address => $name) {
            $from[] = Address::fromString(
                sprintf('%s <%s>', $name, $address)
            );
        }

        $message = (new Email())
            ->subject($subject)
            ->from(...$from)
            ->to(Address::fromString($toEmail));

        if (!empty($htmlBody)) {
            $message = $message
                ->html($htmlBody)
                ->text($textBody);
        } else {
            $message = $message->text($textBody);
        }

        if ($this->logging) {
            $this->messages[] = $message;
            return;
        }

        $this->sfMailer->send($message);
    }

    public function enableLogging()
    {
        $this->logging = true;
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
