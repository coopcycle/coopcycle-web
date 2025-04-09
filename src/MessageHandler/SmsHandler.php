<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\Sms;
use AppBundle\Service\SmsManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SmsHandler
{
    public function __construct(SmsManager $smsManager)
    {
        $this->smsManager = $smsManager;
    }

    public function __invoke(Sms $message)
    {
        $this->smsManager->send($message->getText(), $message->getTo());
    }
}
