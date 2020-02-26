<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\Event;
use AppBundle\Service\SocketIoManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class EventHandler implements MessageHandlerInterface
{
    public function __construct(SocketIoManager $socketIo)
    {
        $this->socketIo = $socketIo;
    }

    public function __invoke(Event $message)
    {
        $this->socketIo->toAdmins($message->getName(), $message->getData());
    }
}
