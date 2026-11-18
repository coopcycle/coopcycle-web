<?php

declare(strict_types=1);

namespace AppBundle\Messenger;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Tells whether the code runs while a worker handles a message, as opposed to
 * an HTTP request or a console command.
 *
 * A worker is already off the request path: putting a message on the async
 * transport from there only puts it in line behind the job the worker is busy
 * with, and behind whatever keeps the other workers busy.
 */
class WorkerMessageContext implements EventSubscriberInterface
{
    private bool $handlingMessage = false;

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onDone',
            WorkerMessageFailedEvent::class => 'onDone',
        ];
    }

    public function onReceived(): void
    {
        $this->handlingMessage = true;
    }

    public function onDone(): void
    {
        $this->handlingMessage = false;
    }

    public function isHandlingMessage(): bool
    {
        return $this->handlingMessage;
    }
}
