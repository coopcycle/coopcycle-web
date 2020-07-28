<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\Email;
use AppBundle\Service\EmailManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class EmailHandler implements MessageHandlerInterface
{
    private \Swift_Mailer $mailer;
    private \Swift_Transport $transport;
    private EmailManager $emailManager;

    public function __construct(\Swift_Mailer $mailer, \Swift_Transport $transport, EmailManager $emailManager)
    {
        $this->mailer = $mailer;
        $this->transport = $transport;
        $this->emailManager = $emailManager;
    }

    public function __invoke(Email $message)
    {
        $this->emailManager->sendTo($message->getMessage(), $message->getTo());
        $transport = $this->mailer->getTransport();
        if ($transport instanceof \Swift_Transport_SpoolTransport) {
            $spool = $transport->getSpool();
            $spool->flushQueue($this->transport);
        }
    }
}
