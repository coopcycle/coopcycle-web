<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\PublishToCentrifugo;
use phpcent\Client as CentrifugoClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Forwards an already-prepared live update to Centrifugo.
 *
 * This handler has no entity manager and no serializer on purpose: there is
 * nothing left to resolve, so there is nothing left to get wrong. Keeping the
 * HTTP call out of the request also means a slow Centrifugo no longer slows down
 * order acceptance or a drag'n'drop on the dispatch board.
 */
#[AsMessageHandler]
class PublishToCentrifugoHandler
{
    public function __construct(
        private CentrifugoClient $centrifugoClient,
        private LoggerInterface $realTimeMessageLogger,
    ) {}

    public function __invoke(PublishToCentrifugo $message): void
    {
        if (empty($message->channels)) {
            return;
        }

        $payload = ['event' => $message->event];

        // broadcast() is a single API call for many channels; publish() is the
        // one-channel form. Centrifugo rejects a broadcast with an empty list.
        $result = 1 === count($message->channels)
            ? $this->centrifugoClient->publish($message->channels[0], $payload)
            : $this->centrifugoClient->broadcast($message->channels, $payload);

        $this->logPublicationError($result, $message);
    }

    /**
     * Centrifugo answers 200 with an `error` object in the body when it refuses a
     * publication, and phpcent only throws on a non-200. A refused publication is
     * otherwise indistinguishable from a delivered one.
     */
    private function logPublicationError($result, PublishToCentrifugo $message): void
    {
        $error = is_array($result) ? ($result['error'] ?? null) : null;

        if (null === $error) {
            return;
        }

        $this->realTimeMessageLogger->error(sprintf(
            "Centrifugo refused event '%s' on %d channel(s): %s",
            $message->event['name'] ?? '?',
            count($message->channels),
            json_encode($error)
        ));
    }
}
