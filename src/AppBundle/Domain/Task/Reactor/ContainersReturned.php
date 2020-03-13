<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event;

class ContainersReturned
{
    public function __invoke(Event $event)
    {
        $data = $event->getTask()->getData();

        if (!isset($data['containers_returned'])) {
            return;
        }

        // TODO
    }
}
