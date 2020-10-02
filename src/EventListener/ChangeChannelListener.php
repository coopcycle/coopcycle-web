<?php

declare(strict_types=1);

namespace AppBundle\EventListener;

use AppBundle\Sylius\Channel\ProChannelContext;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ChangeChannelListener
{
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$event->getRequest()->query->has(ProChannelContext::QUERY_PARAM_NAME)) {
            return;
        }

        $newChannel = $event->getRequest()->query->get(ProChannelContext::QUERY_PARAM_NAME);
        if (!in_array($newChannel, ['web', 'pro'])) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->setCookie(new Cookie(ProChannelContext::COOKIE_KEY, $newChannel));
    }
}
