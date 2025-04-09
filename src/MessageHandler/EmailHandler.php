<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\Email;
use AppBundle\Service\EmailManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EmailHandler
{
    private EmailManager $emailManager;

    public function __construct(EmailManager $emailManager)
    {
        $this->emailManager = $emailManager;
    }

    public function __invoke(Email $message)
    {
        $this->emailManager->sendTo($message->getMessage(), $message->getTo());
    }
}
