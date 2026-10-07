<?php

namespace AppBundle\Message;

/**
 * A live update that is ready to go out: the channels are resolved and the event
 * payload is already serialized.
 *
 * Everything that needs the entity, the Doctrine identity map or the security
 * context has happened by the time this message exists. The handler only forwards
 * it to Centrifugo, so it cannot re-derive -- and therefore cannot get wrong --
 * the state the event is about.
 *
 * This is deliberately not "publish the live update for task 42": a message that
 * names an entity makes the worker read it back, and the row it reads can predate
 * the change the event describes. That produced `task:done` payloads carrying
 * `status=TODO` in production.
 */
final class PublishToCentrifugo
{
    /**
     * @param string[] $channels Fully resolved Centrifugo channel names
     * @param array $event The event envelope: name, data and version
     */
    public function __construct(
        public readonly array $channels,
        public readonly array $event,
    ) {}
}
