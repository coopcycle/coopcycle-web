<?php
declare(strict_types=1);

namespace AppBundle\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ChangeChannelListener
{
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        if (!$event->getRequest()->query->has('change_channel')) {
            return;
        }
        $response = $event->getResponse();
        $newChannel = $event->getRequest()->query->get('change_channel');
        if (!in_array($newChannel, ['web', 'pro'])) {
            return;
        }
        $response->headers->setCookie(new Cookie('channel_cart', $newChannel));

    }
}
