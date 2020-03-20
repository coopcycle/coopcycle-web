<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\Sms;
use AppBundle\Service\SmsManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SmsHandler implements MessageHandlerInterface
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
